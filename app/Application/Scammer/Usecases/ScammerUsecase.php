<?php

namespace App\Application\Scammer\Usecases;

use App\Application\Scammer\Commands\CreateScammerContactCommand;
use App\Application\Scammer\Commands\CreateScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\DeleteScammerCommand;
use App\Application\Scammer\Commands\HasScammerPaymentMethodCommand;
use App\Application\Scammer\Commands\ListScammerContactsCommand;
use App\Application\Scammer\Commands\ListScammerReportsCommand;
use App\Application\Scammer\Commands\ListScammersCommand;
use App\Application\Scammer\Commands\LoadScammerCommand;
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
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Contact\ContactRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Scammer\ScammerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScammerUsecase implements ScammerUsecaseInterface
{
    public function __construct(
        private ScammerRepositoryInterface $scammers,
        private ContactRepositoryInterface $contacts,
        private PaymentMethodRepositoryInterface $paymentMethods,
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

    public function reports(ListScammerReportsCommand $command): ?PaginatedResult
    {
        return $this->scammers->findPaginatedReportsById($command->id, $command->page, $command->count);
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
        return DB::transaction(function () use ($command): Scammer {
            $scammer = $this->scammers->create([
                'name' => trim($command->name),
                'is_active' => $command->isActive,
            ]);

            foreach ($command->contacts as $contactData) {
                $entity = new ContactEntity(
                    id: null,
                    name: $contactData->name,
                    platformType: $contactData->platform,
                    reference: $contactData->reference,
                    isActive: $contactData->isActive,
                );
                $values = $entity->toArray();
                $contact = $this->contacts->firstOrCreate(
                    $values['platform'],
                    $values['reference'],
                    $values['name'],
                    $values['is_active'],
                );
                $this->scammers->attachContact($scammer, $contact->id);
            }

            foreach ($command->paymentMethods as $paymentMethodData) {
                $paymentMethod = $this->paymentMethods->firstOrCreate(
                    $paymentMethodData->type,
                    trim($paymentMethodData->reference),
                    $paymentMethodData->isActive,
                );
                $this->scammers->attachPaymentMethod($scammer, $paymentMethod->id);
            }

            return $this->scammers->loadStored($scammer);
        });
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
                name: $command->name,
                platformType: $command->platform,
                reference: $command->reference,
                isActive: $command->isActive,
            );
            $values = $entity->toArray();
            $contact = $this->contacts->firstOrCreate(
                $values['platform'],
                $values['reference'],
                $values['name'],
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
            throw new \InvalidArgumentException('Platform cannot be empty');
        }

        $entity = new ContactEntity(
            id: $command->contact->id,
            name: $command->nameProvided ? (string) $command->name : $command->contact->name,
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
}
