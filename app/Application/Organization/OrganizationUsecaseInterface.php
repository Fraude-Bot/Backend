<?php

namespace App\Application\Organization;

use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use Illuminate\Support\Collection;

interface OrganizationUsecaseInterface
{
    public function show(int $id): ?Organization;

    public function calendar(int $id, int $year): ?Collection;

    public function contacts(int $id, int $page, int $count, ?string $platform): ?PaginatedResult;

    public function reports(int $id, int $page, int $count): ?PaginatedResult;

    public function map(int $id): ?MapResult;

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

    public function load(Organization $organization): Organization;

    public function scammers(Organization $organization): Collection;

    public function addScammer(Organization $organization, Scammer $scammer): void;

    public function hasPaymentMethod(Organization $organization, mixed $type, string $reference): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPaymentMethod(Organization $organization, array $data): PaymentMethod;
}
