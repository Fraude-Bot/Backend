<?php

namespace App\Repositories\Organization;

use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Organization;
use App\Models\Scammer;
use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    public function findOrganizationById(int $id): ?Organization;

    public function findCalendarByOrganizationIdAndYear(int $id, int $year): ?Collection;

    public function findContactsById(int $id): ?Collection;

    public function findPaginatedContactsById(int $id, int $page, int $count, ?string $platform = null): ?PaginatedResult;

    public function findPaginatedReportsById(int $id, int $page, int $count): ?PaginatedResult;

    public function findMapById(int $id): ?MapResult;

    /**
     * @return list<string>
     */
    public function suggest(string $query): array;

    public function list(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Organization;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Organization $organization, array $attributes): Organization;

    public function delete(Organization $organization): void;

    public function restore(int $id): Organization;

    public function scammers(Organization $organization): Collection;

    public function attachScammer(Organization $organization, Scammer $scammer): void;

    public function attachContact(Organization $organization, int $contactId): void;

    public function attachPaymentMethod(Organization $organization, int $paymentMethodId): void;
}
