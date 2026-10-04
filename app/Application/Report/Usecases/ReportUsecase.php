<?php

namespace App\Application\Report\Usecases;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Application\Report\Commands\ContactInput;
use App\Application\Report\Commands\OrganizationReportScammerInput;
use App\Application\Report\Commands\PaymentMethodInput;
use App\Application\Report\Commands\ScammerReportOrganizationInput;
use App\Application\Report\Commands\SearchReportsCommand;
use App\Application\Report\Commands\StoreOrganizationReportCommand;
use App\Application\Report\Commands\StoreScammerReportCommand;
use App\Application\Report\Commands\StoreTemporaryProfilePictureCommand;
use App\Application\Report\Commands\StoreTemporaryProofsCommand;
use App\Domain\Contact\Entities\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\PaymentMethod\ValueObjects\AccountNumber;
use App\Domain\PaymentMethod\ValueObjects\CardNumber;
use App\Domain\PaymentMethod\ValueObjects\Clabe;
use App\Domain\PaymentMethod\ValueObjects\Reference;
use App\Domain\Search\ValueObjects\CardSearchResult;
use App\Models\Organization;
use App\Models\Report;
use App\Models\Scammer;
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Report\ReportRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use App\Repositories\Search\SearchRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ReportUsecase implements ReportUsecaseInterface
{
    public const string ORGANIZATION_PROFILE_DIRECTORY = 'reports/organizations/profiles';

    public const string SCAMMER_PROFILE_DIRECTORY = 'reports/scammers/profiles';

    public const string ORGANIZATION_PROOF_DIRECTORY = 'reports/organizations/proofs';

    public const string SCAMMER_PROOF_DIRECTORY = 'reports/scammers/proofs';

    public function __construct(
        private SearchRepositoryInterface $search,
        private TemporaryImageStorageInterface $images,
        private OrganizationRepositoryInterface $organizations,
        private ScammerRepositoryInterface $scammers,
        private ReportRepositoryInterface $reports,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
        private ProductRepositoryInterface $products,
    ) {}

    public function search(SearchReportsCommand $command): CardSearchResult
    {
        return $this->search->find($command->clue, $command->page, $command->count);
    }

    public function storeTemporaryProfilePicture(StoreTemporaryProfilePictureCommand $command): string
    {
        return $this->images->uploadProfilePicture($command->file);
    }

    public function storeTemporaryProofs(StoreTemporaryProofsCommand $command): array
    {
        $paths = [];

        foreach ($command->files as $image) {
            $paths[] = $this->images->uploadProof($image);
        }

        return $paths;
    }

    public function storeOrganization(StoreOrganizationReportCommand $command): array
    {
        $contacts = $this->normalizeContacts($command->contacts);
        $paymentMethods = $this->normalizePaymentMethods($command->paymentMethods);
        $scammerPayloads = $this->normalizeScammers($command->scammers);
        $copied = [];

        try {
            $avatarPath = null;

            if (is_string($command->profilePicture) && $command->profilePicture !== '') {
                $avatarPath = $this->copyTemporary(
                    $command->profilePicture,
                    TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                    self::ORGANIZATION_PROFILE_DIRECTORY,
                    'profile_picture',
                );
                $copied[] = $avatarPath;
            }

            $proofPaths = [];

            foreach ($command->proofs as $index => $url) {
                $path = $this->copyTemporary(
                    $url,
                    TemporaryImageStorageInterface::PROOF_DIRECTORY,
                    self::ORGANIZATION_PROOF_DIRECTORY,
                    'proofs.'.$index,
                );
                $copied[] = $path;
                $proofPaths[] = $path;
            }

            return DB::transaction(function () use (
                $command,
                $avatarPath,
                $proofPaths,
                $contacts,
                $paymentMethods,
                $scammerPayloads,
                &$copied,
            ): array {
                $organization = $this->organizations->firstOrCreate($command->organizationName, $avatarPath);
                $this->discardUnusedAvatar($avatarPath, $organization->profile_picture_path, $copied);

                $report = $this->reports->create($command->title, $command->description);
                $this->reports->attachToOrganization($organization, $report);
                $productIds = $this->attachProducts($report, $command->productNames);

                $proofIds = [];

                foreach ($proofPaths as $path) {
                    $proofIds[] = $this->reports->addProof($report, $path);
                }

                $contactIds = [];

                foreach ($contacts as $contact) {
                    $model = $this->contacts->firstOrCreate(
                        $contact['platform'],
                        $contact['reference'],
                        true,
                    );
                    $this->organizations->attachContact($organization, $model->id);
                    $contactIds[] = $model->id;
                }

                $paymentMethodIds = [];

                foreach ($paymentMethods as $paymentMethod) {
                    $model = $this->paymentMethods->firstOrCreate(
                        $paymentMethod['type'],
                        $paymentMethod['reference'],
                        true,
                    );
                    $this->organizations->attachPaymentMethod($organization, $model->id);
                    $paymentMethodIds[] = $model->id;
                }

                $scammerIds = $this->attachScammers($organization, $scammerPayloads);

                return [
                    'id' => $report->id,
                    'organization_id' => $organization->id,
                    'contact_ids' => $contactIds,
                    'payment_method_ids' => $paymentMethodIds,
                    'product_ids' => $productIds,
                    'scammer_ids' => $scammerIds,
                    'report_proof_ids' => $proofIds,
                ];
            });
        } catch (Throwable $exception) {
            foreach ($copied as $path) {
                $this->images->deletePublished($path);
            }

            throw $exception;
        }
    }

    public function storeScammer(StoreScammerReportCommand $command): array
    {
        $contacts = $this->normalizeContacts($command->contacts);
        $paymentMethods = $this->normalizePaymentMethods($command->paymentMethods);
        $organizationPayloads = $this->normalizeOrganizations($command->organizations);
        $copied = [];

        try {
            $avatarPath = null;

            if (is_string($command->profilePicture) && $command->profilePicture !== '') {
                $avatarPath = $this->copyTemporary(
                    $command->profilePicture,
                    TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                    self::SCAMMER_PROFILE_DIRECTORY,
                    'profile_picture',
                );
                $copied[] = $avatarPath;
            }

            $proofPaths = [];

            foreach ($command->proofs as $index => $url) {
                $path = $this->copyTemporary(
                    $url,
                    TemporaryImageStorageInterface::PROOF_DIRECTORY,
                    self::SCAMMER_PROOF_DIRECTORY,
                    'proofs.'.$index,
                );
                $copied[] = $path;
                $proofPaths[] = $path;
            }

            return DB::transaction(function () use (
                $command,
                $avatarPath,
                $proofPaths,
                $contacts,
                $paymentMethods,
                $organizationPayloads,
            ): array {
                $scammer = $this->scammers->create([
                    'name' => $command->scammerName,
                    'profile_picture_path' => $avatarPath,
                    'is_active' => true,
                ]);

                $report = $this->reports->create($command->title, $command->description);
                $this->reports->attachToScammer($scammer, $report);
                $productIds = $this->attachProducts($report, $command->productNames);

                $proofIds = [];

                foreach ($proofPaths as $path) {
                    $proofIds[] = $this->reports->addProof($report, $path);
                }

                $contactIds = [];

                foreach ($contacts as $contact) {
                    $model = $this->contacts->firstOrCreate(
                        $contact['platform'],
                        $contact['reference'],
                        true,
                    );
                    $this->scammers->attachContact($scammer, $model->id);
                    $contactIds[] = $model->id;
                }

                $paymentMethodIds = [];

                foreach ($paymentMethods as $paymentMethod) {
                    $model = $this->paymentMethods->firstOrCreate(
                        $paymentMethod['type'],
                        $paymentMethod['reference'],
                        true,
                    );
                    $this->scammers->attachPaymentMethod($scammer, $model->id);
                    $paymentMethodIds[] = $model->id;
                }

                $organizationIds = $this->attachOrganizations($scammer, $organizationPayloads);

                return [
                    'id' => $report->id,
                    'scammer_id' => $scammer->id,
                    'contact_ids' => $contactIds,
                    'payment_method_ids' => $paymentMethodIds,
                    'product_ids' => $productIds,
                    'organization_ids' => $organizationIds,
                    'report_proof_ids' => $proofIds,
                ];
            });
        } catch (Throwable $exception) {
            foreach ($copied as $path) {
                $this->images->deletePublished($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function attachProducts(Report $report, array $names): array
    {
        $productIds = [];

        foreach ($names as $name) {
            $product = $this->products->firstOrCreate($name);
            $this->reports->attachProduct($report, $product->id);

            if (! in_array($product->id, $productIds, true)) {
                $productIds[] = $product->id;
            }
        }

        return $productIds;
    }

    private function copyTemporary(string $url, string $sourceDirectory, string $destinationDirectory, string $errorKey): string
    {
        try {
            return $this->images->copyPublishedTemporary($url, $sourceDirectory, $destinationDirectory);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                $errorKey => ['The image is not a published temporary file.'],
            ]);
        }
    }

    /**
     * @param  list<OrganizationReportScammerInput>  $scammers
     * @return list<array{name: string, contacts: list<array{platform: PlatformType, reference: string}>, payment_methods: list<array{type: PaymentMethodType, reference: string}>}>
     */
    private function normalizeScammers(array $scammers): array
    {
        $normalized = [];

        foreach ($scammers as $index => $scammer) {
            $normalized[] = [
                'name' => $scammer->name,
                'contacts' => $this->normalizeContacts($scammer->contacts, "scammers.{$index}.contacts"),
                'payment_methods' => $this->normalizePaymentMethods($scammer->paymentMethods, "scammers.{$index}.payment_methods"),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{name: string, contacts: list<array{platform: PlatformType, reference: string}>, payment_methods: list<array{type: PaymentMethodType, reference: string}>}>  $scammers
     * @return list<int>
     */
    private function attachScammers(Organization $organization, array $scammers): array
    {
        $scammerIds = [];

        foreach ($scammers as $scammer) {
            $model = $this->scammers->firstOrCreate($scammer['name']);
            $this->organizations->attachScammer($organization, $model);

            foreach ($scammer['contacts'] as $contact) {
                $contactModel = $this->contacts->firstOrCreate(
                    $contact['platform'],
                    $contact['reference'],
                    true,
                );
                $this->scammers->attachContact($model, $contactModel->id);
            }

            foreach ($scammer['payment_methods'] as $paymentMethod) {
                $paymentMethodModel = $this->paymentMethods->firstOrCreate(
                    $paymentMethod['type'],
                    $paymentMethod['reference'],
                    true,
                );
                $this->scammers->attachPaymentMethod($model, $paymentMethodModel->id);
            }

            if (! in_array($model->id, $scammerIds, true)) {
                $scammerIds[] = $model->id;
            }
        }

        return $scammerIds;
    }

    /**
     * @param  list<ScammerReportOrganizationInput>  $organizations
     * @return list<array{name: string, contacts: list<array{platform: PlatformType, reference: string}>, payment_methods: list<array{type: PaymentMethodType, reference: string}>}>
     */
    private function normalizeOrganizations(array $organizations): array
    {
        $normalized = [];

        foreach ($organizations as $index => $organization) {
            $normalized[] = [
                'name' => $organization->name,
                'contacts' => $this->normalizeContacts($organization->contacts, "organizations.{$index}.contacts"),
                'payment_methods' => $this->normalizePaymentMethods($organization->paymentMethods, "organizations.{$index}.payment_methods"),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{name: string, contacts: list<array{platform: PlatformType, reference: string}>, payment_methods: list<array{type: PaymentMethodType, reference: string}>}>  $organizations
     * @return list<int>
     */
    private function attachOrganizations(Scammer $scammer, array $organizations): array
    {
        $organizationIds = [];

        foreach ($organizations as $organization) {
            $model = $this->organizations->firstOrCreate($organization['name'], null);
            $this->organizations->attachScammer($model, $scammer);

            foreach ($organization['contacts'] as $contact) {
                $contactModel = $this->contacts->firstOrCreate(
                    $contact['platform'],
                    $contact['reference'],
                    true,
                );
                $this->organizations->attachContact($model, $contactModel->id);
            }

            foreach ($organization['payment_methods'] as $paymentMethod) {
                $paymentMethodModel = $this->paymentMethods->firstOrCreate(
                    $paymentMethod['type'],
                    $paymentMethod['reference'],
                    true,
                );
                $this->organizations->attachPaymentMethod($model, $paymentMethodModel->id);
            }

            if (! in_array($model->id, $organizationIds, true)) {
                $organizationIds[] = $model->id;
            }
        }

        return $organizationIds;
    }

    /**
     * @param  list<string>  $copied
     */
    private function discardUnusedAvatar(?string $copiedPath, ?string $storedPath, array &$copied): void
    {
        if (! is_string($copiedPath) || $copiedPath === $storedPath) {
            return;
        }

        $this->images->deletePublished($copiedPath);
        $copied = array_values(array_filter(
            $copied,
            fn (string $path): bool => $path !== $copiedPath,
        ));
    }

    /**
     * @param  list<ContactInput>  $contacts
     * @return list<array{platform: PlatformType, reference: string}>
     */
    private function normalizeContacts(array $contacts, string $key = 'contacts'): array
    {
        $normalized = [];

        foreach ($contacts as $index => $contact) {
            try {
                $entity = new ContactEntity(
                    id: null,
                    platformType: $contact->platform,
                    reference: $contact->reference,
                    isActive: true,
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "{$key}.{$index}.reference" => [$exception->getMessage()],
                ]);
            }

            $values = $entity->toArray();
            $normalized[] = [
                'platform' => $values['platform'],
                'reference' => $values['reference'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<PaymentMethodInput>  $paymentMethods
     * @return list<array{type: PaymentMethodType, reference: string}>
     */
    private function normalizePaymentMethods(array $paymentMethods, string $key = 'payment_methods'): array
    {
        $normalized = [];

        foreach ($paymentMethods as $index => $paymentMethod) {
            try {
                $reference = $this->normalizePaymentReference($paymentMethod->type, $paymentMethod->reference);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "{$key}.{$index}.reference" => [$exception->getMessage()],
                ]);
            }

            $normalized[] = [
                'type' => $paymentMethod->type,
                'reference' => $reference,
            ];
        }

        return $normalized;
    }

    private function normalizePaymentReference(PaymentMethodType $type, string $reference): string
    {
        return match ($type) {
            PaymentMethodType::CARD_NUMBER => (string) new CardNumber($reference),
            PaymentMethodType::CLABE => (string) new Clabe($reference),
            PaymentMethodType::ACCOUNT_NUMBER => (string) new AccountNumber($reference),
            PaymentMethodType::WALLET, PaymentMethodType::OTHER => (string) new Reference($reference),
        };
    }
}
