<?php

namespace App\Application\Report\Usecases;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Application\Report\Commands\ContactInput;
use App\Application\Report\Commands\PaymentMethodInput;
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
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Report\ReportRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use App\Repositories\Search\SearchRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ReportUsecase implements ReportUsecaseInterface
{
    public const string AVATAR_DIRECTORY = 'reports/organizations/avatars';

    public const string SCAMMER_AVATAR_DIRECTORY = 'reports/scammers/avatars';

    public const string PROOF_DIRECTORY = 'reports/proofs';

    public function __construct(
        private SearchRepositoryInterface $search,
        private TemporaryImageStorageInterface $images,
        private OrganizationRepositoryInterface $organizations,
        private ScammerRepositoryInterface $scammers,
        private ReportRepositoryInterface $reports,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
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
        $copied = [];

        try {
            $avatarPath = null;

            if (is_string($command->profilePicture) && $command->profilePicture !== '') {
                $avatarPath = $this->copyTemporary(
                    $command->profilePicture,
                    TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                    self::AVATAR_DIRECTORY,
                    'profile_picture',
                );
                $copied[] = $avatarPath;
            }

            $proofPaths = [];

            foreach ($command->proofs as $index => $url) {
                $path = $this->copyTemporary(
                    $url,
                    TemporaryImageStorageInterface::PROOF_DIRECTORY,
                    self::PROOF_DIRECTORY,
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
            ): array {
                $organization = $this->organizations->create([
                    'name' => $command->organizationName,
                    'profile_picture_path' => $avatarPath,
                    'is_active' => true,
                ]);

                $report = $this->reports->create($command->title, $command->description);
                $this->reports->attachToOrganization($organization, $report);

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

                return [
                    'id' => $report->id,
                    'organization_id' => $organization->id,
                    'contact_ids' => $contactIds,
                    'payment_method_ids' => $paymentMethodIds,
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
        $copied = [];

        try {
            $avatarPath = null;

            if (is_string($command->profilePicture) && $command->profilePicture !== '') {
                $avatarPath = $this->copyTemporary(
                    $command->profilePicture,
                    TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                    self::SCAMMER_AVATAR_DIRECTORY,
                    'profile_picture',
                );
                $copied[] = $avatarPath;
            }

            $proofPaths = [];

            foreach ($command->proofs as $index => $url) {
                $path = $this->copyTemporary(
                    $url,
                    TemporaryImageStorageInterface::PROOF_DIRECTORY,
                    self::PROOF_DIRECTORY,
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
            ): array {
                $scammer = $this->scammers->create([
                    'name' => $command->scammerName,
                    'profile_picture_path' => $avatarPath,
                    'is_active' => true,
                ]);

                $report = $this->reports->create($command->title, $command->description);
                $this->reports->attachToScammer($scammer, $report);

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

                return [
                    'id' => $report->id,
                    'scammer_id' => $scammer->id,
                    'contact_ids' => $contactIds,
                    'payment_method_ids' => $paymentMethodIds,
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
     * @param  list<ContactInput>  $contacts
     * @return list<array{platform: PlatformType, reference: string}>
     */
    private function normalizeContacts(array $contacts): array
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
                    "contacts.$index.reference" => [$exception->getMessage()],
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
    private function normalizePaymentMethods(array $paymentMethods): array
    {
        $normalized = [];

        foreach ($paymentMethods as $index => $paymentMethod) {
            try {
                $reference = $this->normalizePaymentReference($paymentMethod->type, $paymentMethod->reference);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "payment_methods.$index.reference" => [$exception->getMessage()],
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
