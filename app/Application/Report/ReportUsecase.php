<?php

namespace App\Application\Report;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Domain\Contact\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\PaymentMethod\ValueObjects\AccountNumber;
use App\Domain\PaymentMethod\ValueObjects\CardNumber;
use App\Domain\PaymentMethod\ValueObjects\Clabe;
use App\Domain\PaymentMethod\ValueObjects\Reference;
use App\Domain\Scammer\ValueObjects\Clue;
use App\Domain\Search\ValueObjects\CardSearchResult;
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Report\ReportRepositoryInterface;
use App\Repositories\Search\SearchRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ReportUsecase implements ReportUsecaseInterface
{
    public const string AVATAR_DIRECTORY = 'reports/organizations/avatars';

    public const string PROOF_DIRECTORY = 'reports/proofs';

    public function __construct(
        private SearchRepositoryInterface $search,
        private TemporaryImageStorageInterface $images,
        private OrganizationRepositoryInterface $organizations,
        private ReportRepositoryInterface $reports,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
    ) {}

    public function search(Clue $clue, int $page, int $count): CardSearchResult
    {
        return $this->search->find($clue, $page, $count);
    }

    public function storeTemporaryProfilePicture(UploadedFile $file): string
    {
        return $this->images->uploadProfilePicture($file);
    }

    public function storeTemporaryProofs(array $files): array
    {
        $paths = [];

        foreach ($files as $image) {
            $paths[] = $this->images->uploadProof($image);
        }

        return $paths;
    }

    public function storeOrganization(array $input): array
    {
        $contacts = $this->normalizeContacts($input['contacts'] ?? []);
        $paymentMethods = $this->normalizePaymentMethods($input['payment_methods'] ?? []);
        $copied = [];

        try {
            $avatarPath = null;
            $profilePicture = $input['profile_picture'] ?? null;

            if (is_string($profilePicture) && $profilePicture !== '') {
                $avatarPath = $this->copyTemporary(
                    $profilePicture,
                    TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                    self::AVATAR_DIRECTORY,
                    'profile_picture',
                );
                $copied[] = $avatarPath;
            }

            $proofPaths = [];

            foreach ($input['proofs'] ?? [] as $index => $url) {
                $path = $this->copyTemporary(
                    is_string($url) ? $url : '',
                    TemporaryImageStorageInterface::PROOF_DIRECTORY,
                    self::PROOF_DIRECTORY,
                    'proofs.'.$index,
                );
                $copied[] = $path;
                $proofPaths[] = $path;
            }

            $organizationInput = is_array($input['organization'] ?? null) ? $input['organization'] : [];
            $organizationName = (string) ($organizationInput['name'] ?? '');
            $title = (string) ($input['title'] ?? '');
            $description = is_string($input['description'] ?? null) ? $input['description'] : null;

            return DB::transaction(function () use (
                $avatarPath,
                $proofPaths,
                $contacts,
                $paymentMethods,
                $organizationName,
                $title,
                $description,
            ): array {
                $organization = $this->organizations->create([
                    'name' => $organizationName,
                    'profile_picture_path' => $avatarPath,
                    'is_active' => true,
                ]);

                $report = $this->reports->create($title, $description);
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
                        $contact['name'],
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
     * @param  list<array<string, mixed>>  $contacts
     * @return list<array{name: string, platform: PlatformType, reference: string}>
     */
    private function normalizeContacts(array $contacts): array
    {
        $normalized = [];

        foreach ($contacts as $index => $contact) {
            try {
                $entity = new ContactEntity(
                    id: null,
                    name: (string) $contact['name'],
                    platformType: PlatformType::from((int) $contact['platform']),
                    reference: (string) $contact['reference'],
                    isActive: true,
                );
            } catch (InvalidArgumentException $exception) {
                $field = str_contains($exception->getMessage(), 'Name') ? 'name' : 'reference';

                throw ValidationException::withMessages([
                    "contacts.$index.$field" => [$exception->getMessage()],
                ]);
            }

            $values = $entity->toArray();
            $normalized[] = [
                'name' => $values['name'],
                'platform' => $values['platform'],
                'reference' => $values['reference'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $paymentMethods
     * @return list<array{type: PaymentMethodType, reference: string}>
     */
    private function normalizePaymentMethods(array $paymentMethods): array
    {
        $normalized = [];

        foreach ($paymentMethods as $index => $paymentMethod) {
            try {
                $type = PaymentMethodType::from((int) $paymentMethod['type']);
                $reference = $this->normalizePaymentReference($type, (string) $paymentMethod['reference']);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "payment_methods.$index.reference" => [$exception->getMessage()],
                ]);
            }

            $normalized[] = [
                'type' => $type,
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
