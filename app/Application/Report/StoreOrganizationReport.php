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
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Report;
use App\Models\ReportProof;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class StoreOrganizationReport
{
    public const string AVATAR_DIRECTORY = 'reports/organizations/avatars';

    public const string PROOF_DIRECTORY = 'reports/proofs';

    public function __construct(private TemporaryImageStorageInterface $images) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{id: int, organization_id: int, contact_ids: list<int>, payment_method_ids: list<int>, report_proof_ids: list<int>}
     */
    public function store(array $input): array
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
                $organization = Organization::create([
                    'name' => $organizationName,
                    'profile_picture_path' => $avatarPath,
                    'is_active' => true,
                ]);

                $report = Report::create([
                    'user_id' => null,
                    'title' => $title,
                    'description' => $description,
                    'is_active' => true,
                ]);

                $organization->reports()->syncWithoutDetaching([$report->id]);

                $proofIds = [];

                foreach ($proofPaths as $path) {
                    $proofIds[] = ReportProof::query()->create([
                        'report_id' => $report->id,
                        'path' => $path,
                    ])->id;
                }

                $contactIds = [];

                foreach ($contacts as $contact) {
                    $model = Contact::withTrashed()->firstOrCreate(
                        ['platform' => $contact['platform'], 'reference' => $contact['reference']],
                        ['name' => $contact['name'], 'is_active' => true],
                    );

                    if ($model->trashed()) {
                        $model->restore();
                    }

                    $organization->contacts()->syncWithoutDetaching([$model->id]);
                    $contactIds[] = $model->id;
                }

                $paymentMethodIds = [];

                foreach ($paymentMethods as $paymentMethod) {
                    $model = PaymentMethod::withTrashed()->firstOrCreate(
                        ['type' => $paymentMethod['type'], 'reference' => $paymentMethod['reference']],
                        ['is_active' => true],
                    );

                    if ($model->trashed()) {
                        $model->restore();
                    }

                    $organization->paymentMethods()->syncWithoutDetaching([$model->id]);
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
