<?php

namespace App\Repositories\Scammer;

use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\Scammer;
use Illuminate\Support\Collection;

interface ScammerRepositoryInterface
{
    public function findScammerById(int $id): ?Scammer;

    public function findCalendarByScammerIdAndYear(int $id, int $year): ?Collection;

    public function findContactsById(int $id): ?Collection;

    public function findPaginatedContactsById(int $id, int $page, int $count, ?string $platform = null): ?PaginatedResult;

    public function findPaginatedReportsById(int $id, int $page, int $count): ?PaginatedResult;

    public function findMapById(int $id): ?MapResult;

    /**
     * @return list<string>
     */
    public function suggest(string $query): array;

    public function list(): Collection;

    public function loadDetails(Scammer $scammer): Scammer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Scammer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Scammer $scammer, array $attributes): Scammer;

    public function delete(Scammer $scammer): void;

    public function restore(int $id): Scammer;

    public function loadStored(Scammer $scammer): Scammer;

    public function attachContact(Scammer $scammer, int $contactId): void;

    public function attachPaymentMethod(Scammer $scammer, int $paymentMethodId): void;

    public function hasContact(Scammer $scammer, Contact $contact): bool;
}
