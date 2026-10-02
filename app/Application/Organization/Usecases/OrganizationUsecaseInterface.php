<?php

namespace App\Application\Organization\Usecases;

use App\Application\Organization\Commands\AddOrganizationScammerCommand;
use App\Application\Organization\Commands\CreateOrganizationCommand;
use App\Application\Organization\Commands\CreateOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\DeleteOrganizationCommand;
use App\Application\Organization\Commands\HasOrganizationPaymentMethodCommand;
use App\Application\Organization\Commands\ListOrganizationContactsCommand;
use App\Application\Organization\Commands\ListOrganizationReportsCommand;
use App\Application\Organization\Commands\ListOrganizationScammersCommand;
use App\Application\Organization\Commands\ListOrganizationsCommand;
use App\Application\Organization\Commands\LoadOrganizationCommand;
use App\Application\Organization\Commands\OrganizationCalendarCommand;
use App\Application\Organization\Commands\OrganizationMapCommand;
use App\Application\Organization\Commands\RestoreOrganizationCommand;
use App\Application\Organization\Commands\ShowOrganizationCommand;
use App\Application\Organization\Commands\SuggestOrganizationsCommand;
use App\Application\Organization\Commands\UpdateOrganizationCommand;
use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Organization;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

interface OrganizationUsecaseInterface
{
    public function show(ShowOrganizationCommand $command): ?Organization;

    public function calendar(OrganizationCalendarCommand $command): ?Collection;

    public function contacts(ListOrganizationContactsCommand $command): ?PaginatedResult;

    public function reports(ListOrganizationReportsCommand $command): ?PaginatedResult;

    public function map(OrganizationMapCommand $command): ?MapResult;

    /**
     * @return list<string>
     */
    public function suggest(SuggestOrganizationsCommand $command): array;

    public function list(ListOrganizationsCommand $command): Collection;

    public function create(CreateOrganizationCommand $command): Organization;

    public function update(UpdateOrganizationCommand $command): Organization;

    public function delete(DeleteOrganizationCommand $command): void;

    public function restore(RestoreOrganizationCommand $command): Organization;

    public function load(LoadOrganizationCommand $command): Organization;

    public function scammers(ListOrganizationScammersCommand $command): Collection;

    public function addScammer(AddOrganizationScammerCommand $command): void;

    public function hasPaymentMethod(HasOrganizationPaymentMethodCommand $command): bool;

    public function createPaymentMethod(CreateOrganizationPaymentMethodCommand $command): PaymentMethod;
}
