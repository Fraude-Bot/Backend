<?php

namespace App\Application\Scammer\Usecases;

use App\Application\Media\TemporaryImageStorageInterface;
use App\Application\Scammer\Commands\ContactInput;
use App\Application\Scammer\Commands\CreateScammerContactCommand;
use App\Application\Scammer\Commands\CreateScammerOrganizationCommand;
use App\Application\Scammer\Commands\CreateScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\DeleteScammerCommand;
use App\Application\Scammer\Commands\HasScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\ListPublicScammerOrganizationsCommand;
use App\Application\Scammer\Commands\ListScammerContactsCommand;
use App\Application\Scammer\Commands\ListScammerPaymentMethodsCommand;
use App\Application\Scammer\Commands\ListScammerReportsCommand;
use App\Application\Scammer\Commands\ListScammersCommand;
use App\Application\Scammer\Commands\LoadScammerCommand;
use App\Application\Scammer\Commands\PaymentMethodInput;
use App\Application\Scammer\Commands\RestoreScammerCommand;
use App\Application\Scammer\Commands\ScammerCalendarCommand;
use App\Application\Scammer\Commands\ScammerMapCommand;
use App\Application\Scammer\Commands\ShowScammerCommand;
use App\Application\Scammer\Commands\StoreScammerCommand;
use App\Application\Scammer\Commands\SuggestScammersCommand;
use App\Application\Scammer\Commands\UpdateScammerCommand;
use App\Application\Scammer\Commands\UpdateScammerContactCommand;
use App\Domain\Contact\Entities\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\PaymentMethod\ValueObjects\AccountNumber;
use App\Domain\PaymentMethod\ValueObjects\CardNumber;
use App\Domain\PaymentMethod\ValueObjects\Clabe;
use App\Domain\PaymentMethod\ValueObjects\Reference;
use App\Domain\Scammer\Exceptions\InvalidOrganizationReferenceException;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ScammerUsecase implements ScammerUsecaseInterface
{
    private const string ORGANIZATION_PROFILE_DIRECTORY = 'reports/organizations/profiles';

    public function __construct(
        private ScammerRepositoryInterface $scammers,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
        private OrganizationRepositoryInterface $organizations,
        private TemporaryImageStorageInterface $images,
    ) {}

    public function show(ShowScammerCommand $command): ?Scammer
    {
        return $this->scammers->findScammerById($command->id);
    }

    public function calendar(ScammerCalendarCommand $command): ?Collection
    {
        return $this->scammers->findCalendarByScammerIdAndYear($command->id, $command->year);
    }

    public function contacts(ListScammerContactsCommand $command): ?PaginatedResult
    {
        return $this->scammers->findPaginatedContactsById($command->id, $command->page, $command->count, $command->platform);
    }

    public function paymentMethods(ListScammerPaymentMethodsCommand $command): ?PaginatedResult
    {
        return $this->scammers->findPaginatedPaymentMethodsById($command->id, $command->page, $command->count, $command->type);
    }

    public function reports(ListScammerReportsCommand $command): ?PaginatedResult
    {
        return $this->scammers->findPaginatedReportsById($command->id, $command->page, $command->count);
    }

    public function listOrganizations(ListPublicScammerOrganizationsCommand $command): ?PaginatedResult
    {
        return $this->scammers->findPaginatedOrganizationsById($command->id, $command->page, $command->count);
    }

    public function map(ScammerMapCommand $command): ?MapResult
    {
        return $this->scammers->findMapById($command->id);
    }

    public function suggest(SuggestScammersCommand $command): array
    {
        return $this->scammers->suggest($command->query);
    }

    public function list(ListScammersCommand $command): Collection
    {
        return $this->scammers->list();
    }

    public function load(LoadScammerCommand $command): Scammer
    {
        return $this->scammers->loadDetails($command->scammer);
    }

    public function store(StoreScammerCommand $command): Scammer
    {
        return $this->scammers->create([
            'name' => trim($command->name),
            'is_active' => $command->isActive,
        ]);
    }

    public function update(UpdateScammerCommand $command): Scammer
    {
        return $this->scammers->update($command->scammer, $command->attributes());
    }

    public function delete(DeleteScammerCommand $command): void
    {
        $this->scammers->delete($command->scammer);
    }

    public function restore(RestoreScammerCommand $command): Scammer
    {
        return $this->scammers->restore($command->id);
    }

    public function createContact(CreateScammerContactCommand $command): Contact
    {
        return DB::transaction(function () use ($command): Contact {
            $entity = new ContactEntity(
                id: null,
                platformType: $command->platform,
                reference: $command->reference,
                isActive: $command->isActive,
            );
            $values = $entity->toArray();
            $contact = $this->contacts->firstOrCreate(
                $values['platform'],
                $values['reference'],
                $values['is_active'],
            );
            $this->scammers->attachContact($command->scammer, $contact->id);

            return $contact;
        });
    }

    public function updateContact(UpdateScammerContactCommand $command): ?Contact
    {
        if (! $this->scammers->hasContact($command->scammer, $command->contact)) {
            return null;
        }

        $platform = $command->contact->platform;

        if ($command->platformProvided) {
            $platform = $command->platform;
        }

        if (! $platform instanceof PlatformType) {
            throw new InvalidArgumentException('Platform cannot be empty');
        }

        $entity = new ContactEntity(
            id: $command->contact->id,
            platformType: $platform,
            reference: $command->referenceProvided ? (string) $command->reference : $command->contact->reference,
            isActive: $command->isActiveProvided ? (bool) $command->isActive : $command->contact->is_active,
        );

        return $this->contacts->update($command->contact, $entity->toArray());
    }

    public function hasPaymentMethod(HasScammerPaymentMethodCommand $command): bool
    {
        return $this->paymentMethods->linkedToScammer($command->scammer, $command->type, $command->reference);
    }

    public function createPaymentMethod(CreateScammerPaymentMethodCommand $command): PaymentMethod
    {
        return DB::transaction(function () use ($command): PaymentMethod {
            $paymentMethod = $this->paymentMethods->firstOrCreate(
                $command->type,
                trim($command->reference),
                $command->isActive,
            );
            $this->scammers->attachPaymentMethod($command->scammer, $paymentMethod->id);

            return $paymentMethod;
        });
    }

    public function createOrganization(CreateScammerOrganizationCommand $command): Organization
    {
        $contacts = $this->normalizeContacts($command->contacts);
        $paymentMethods = $this->normalizePaymentMethods($command->paymentMethods);
        $copied = [];

        try {
            $avatarPath = null;

            if (is_string($command->profilePicturePath) && $command->profilePicturePath !== '') {
                $avatarPath = $this->copyTemporaryProfilePicture($command->profilePicturePath);
                $copied[] = $avatarPath;
            }

            return DB::transaction(function () use ($command, $avatarPath, &$copied, $contacts, $paymentMethods): Organization {
                $organization = $this->organizations->firstOrCreate($command->name, $avatarPath);
                $this->discardUnusedAvatar($avatarPath, $organization->profile_picture_path, $copied);
                $this->scammers->attachOrganization($command->scammer, $organization);

                foreach ($contacts as $contact) {
                    $model = $this->contacts->firstOrCreate(
                        $contact['platform'],
                        $contact['reference'],
                        true,
                    );
                    $this->organizations->attachContact($organization, $model->id);
                }

                foreach ($paymentMethods as $paymentMethod) {
                    $model = $this->paymentMethods->firstOrCreate(
                        $paymentMethod['type'],
                        $paymentMethod['reference'],
                        true,
                    );
                    $this->organizations->attachPaymentMethod($organization, $model->id);
                }

                return $organization;
            });
        } catch (Throwable $exception) {
            foreach ($copied as $path) {
                $this->images->deletePublished($path);
            }

            throw $exception;
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
                throw new InvalidOrganizationReferenceException(
                    "contacts.{$index}.reference",
                    $exception->getMessage(),
                );
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
                throw new InvalidOrganizationReferenceException(
                    "payment_methods.{$index}.reference",
                    $exception->getMessage(),
                );
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

    private function copyTemporaryProfilePicture(string $location): string
    {
        try {
            return $this->images->copyPublishedTemporary(
                $location,
                TemporaryImageStorageInterface::PROFILE_PICTURE_DIRECTORY,
                self::ORGANIZATION_PROFILE_DIRECTORY,
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'profile_picture_path' => ['The image is not a published temporary file.'],
            ]);
        }
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
}
