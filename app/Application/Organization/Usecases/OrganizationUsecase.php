<?php

namespace App\Application\Organization\Usecases;

use App\Application\Organization\Commands\AddOrganizationScammerCommand;
use App\Application\Organization\Commands\CreateOrganizationCommand;
use App\Application\Organization\Commands\CreateOrganizationContactCommand;
use App\Application\Organization\Commands\CreateOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\CreateOrganizationScammerCommand;
use App\Application\Organization\Commands\DeleteOrganizationCommand;
use App\Application\Organization\Commands\HasOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\ListOrganizationContactsCommand;
use App\Application\Organization\Commands\ListOrganizationPaymentMethodsCommand;
use App\Application\Organization\Commands\ListOrganizationReportsCommand;
use App\Application\Organization\Commands\ListOrganizationScammersCommand;
use App\Application\Organization\Commands\ListPublicOrganizationScammersCommand;
use App\Application\Organization\Commands\ListOrganizationsCommand;
use App\Application\Organization\Commands\LoadOrganizationCommand;
use App\Application\Organization\Commands\OrganizationCalendarCommand;
use App\Application\Organization\Commands\OrganizationMapCommand;
use App\Application\Organization\Commands\RestoreOrganizationCommand;
use App\Application\Organization\Commands\ShowOrganizationCommand;
use App\Application\Organization\Commands\SuggestOrganizationsCommand;
use App\Application\Organization\Commands\UpdateOrganizationCommand;
use App\Domain\Contact\Entities\ContactEntity;
use App\Domain\Map\ValueObjects\MapResult;
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

class OrganizationUsecase implements OrganizationUsecaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private PaymentMethodRepositoryInterface $paymentMethods,
        private ScammerRepositoryInterface $scammers,
        private ContactRepositoryInterface $contacts,
    ) {}

    public function show(ShowOrganizationCommand $command): ?Organization
    {
        return $this->organizations->findOrganizationById($command->id);
    }

    public function calendar(OrganizationCalendarCommand $command): ?Collection
    {
        return $this->organizations->findCalendarByOrganizationIdAndYear($command->id, $command->year);
    }

    public function contacts(ListOrganizationContactsCommand $command): ?PaginatedResult
    {
        return $this->organizations->findPaginatedContactsById($command->id, $command->page, $command->count, $command->platform);
    }

    public function paymentMethods(ListOrganizationPaymentMethodsCommand $command): ?PaginatedResult
    {
        return $this->organizations->findPaginatedPaymentMethodsById($command->id, $command->page, $command->count, $command->type);
    }

    public function reports(ListOrganizationReportsCommand $command): ?PaginatedResult
    {
        return $this->organizations->findPaginatedReportsById($command->id, $command->page, $command->count);
    }

    public function listScammers(ListPublicOrganizationScammersCommand $command): ?PaginatedResult
    {
        return $this->organizations->findPaginatedScammersById($command->id, $command->page, $command->count);
    }

    public function map(OrganizationMapCommand $command): ?MapResult
    {
        return $this->organizations->findMapById($command->id);
    }

    public function suggest(SuggestOrganizationsCommand $command): array
    {
        return $this->organizations->suggest($command->query);
    }

    public function list(ListOrganizationsCommand $command): Collection
    {
        return $this->organizations->list();
    }

    public function create(CreateOrganizationCommand $command): Organization
    {
        return $this->organizations->create($command->attributes());
    }

    public function update(UpdateOrganizationCommand $command): Organization
    {
        return $this->organizations->update($command->organization, $command->attributes());
    }

    public function delete(DeleteOrganizationCommand $command): void
    {
        $this->organizations->delete($command->organization);
    }

    public function restore(RestoreOrganizationCommand $command): Organization
    {
        return $this->organizations->restore($command->id);
    }

    public function load(LoadOrganizationCommand $command): Organization
    {
        return $command->organization;
    }

    public function scammers(ListOrganizationScammersCommand $command): Collection
    {
        return $this->organizations->scammers($command->organization);
    }

    public function addScammer(AddOrganizationScammerCommand $command): void
    {
        $scammer = $this->scammers->findById($command->scammerId);
        $this->organizations->attachScammer($command->organization, $scammer);
    }

    public function hasPaymentMethod(HasOrganizationPaymentMethodCommand $command): bool
    {
        return $this->paymentMethods->linkedToOrganization($command->organization, $command->type, $command->reference);
    }

    public function createPaymentMethod(CreateOrganizationPaymentMethodCommand $command): PaymentMethod
    {
        return DB::transaction(function () use ($command): PaymentMethod {
            $paymentMethod = $this->paymentMethods->firstOrCreate(
                $command->type,
                trim($command->reference),
                $command->isActive,
            );
            $this->organizations->attachPaymentMethod($command->organization, $paymentMethod->id);

            return $paymentMethod;
        });
    }

    public function createContact(CreateOrganizationContactCommand $command): Contact
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
            $this->organizations->attachContact($command->organization, $contact->id);

            return $contact;
        });
    }

    public function createScammer(CreateOrganizationScammerCommand $command): Scammer
    {
        return DB::transaction(function () use ($command): Scammer {
            $scammer = $this->scammers->firstOrCreate($command->name, null);
            $this->organizations->attachScammer($command->organization, $scammer);

            return $scammer;
        });
    }
}
