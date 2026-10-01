<?php

namespace App\Application\Scammer;

use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Contact;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use Illuminate\Support\Collection;

interface ScammerUsecaseInterface
{
    public function show(int $id): ?Scammer;

    public function calendar(int $id, int $year): ?Collection;

    public function contacts(int $id, int $page, int $count, ?string $platform): ?PaginatedResult;

    public function reports(int $id, int $page, int $count): ?PaginatedResult;

    public function map(int $id): ?MapResult;

    public function list(): Collection;

    public function load(Scammer $scammer): Scammer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Scammer;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Scammer $scammer, array $attributes): Scammer;

    public function delete(Scammer $scammer): void;

    public function restore(int $id): Scammer;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createContact(Scammer $scammer, array $data): Contact;

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateContact(Scammer $scammer, Contact $contact, array $input, bool $platformProvided): ?Contact;

    public function hasPaymentMethod(Scammer $scammer, mixed $type, string $reference): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPaymentMethod(Scammer $scammer, array $data): PaymentMethod;
}
