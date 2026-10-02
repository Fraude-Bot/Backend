<?php

namespace App\Application\Organization;

use App\Domain\Map\ValueObjects\MapResult;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Domain\Search\ValueObjects\PaginatedResult;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;
use App\Repositories\Organization\OrganizationRepositoryInterface;
use App\Repositories\PaymentMethod\PaymentMethodRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationUsecase implements OrganizationUsecaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private PaymentMethodRepositoryInterface $paymentMethods,
    ) {}

    public function show(int $id): ?Organization
    {
        return $this->organizations->findOrganizationById($id);
    }

    public function calendar(int $id, int $year): ?Collection
    {
        return $this->organizations->findCalendarByOrganizationIdAndYear($id, $year);
    }

    public function contacts(int $id, int $page, int $count, ?string $platform): ?PaginatedResult
    {
        return $this->organizations->findPaginatedContactsById($id, $page, $count, $platform);
    }

    public function reports(int $id, int $page, int $count): ?PaginatedResult
    {
        return $this->organizations->findPaginatedReportsById($id, $page, $count);
    }

    public function map(int $id): ?MapResult
    {
        return $this->organizations->findMapById($id);
    }

    public function suggest(string $query): array
    {
        return $this->organizations->suggest($query);
    }

    public function list(): Collection
    {
        return $this->organizations->list();
    }

    public function create(array $attributes): Organization
    {
        return $this->organizations->create($attributes);
    }

    public function update(Organization $organization, array $attributes): Organization
    {
        return $this->organizations->update($organization, $attributes);
    }

    public function delete(Organization $organization): void
    {
        $this->organizations->delete($organization);
    }

    public function restore(int $id): Organization
    {
        return $this->organizations->restore($id);
    }

    public function load(Organization $organization): Organization
    {
        return $organization;
    }

    public function scammers(Organization $organization): Collection
    {
        return $this->organizations->scammers($organization);
    }

    public function addScammer(Organization $organization, Scammer $scammer): void
    {
        $this->organizations->attachScammer($organization, $scammer);
    }

    public function hasPaymentMethod(Organization $organization, mixed $type, string $reference): bool
    {
        return $this->paymentMethods->linkedToOrganization($organization, $type, $reference);
    }

    public function createPaymentMethod(Organization $organization, array $data): PaymentMethod
    {
        return DB::transaction(function () use ($organization, $data): PaymentMethod {
            $paymentMethod = $this->paymentMethods->firstOrCreate(
                PaymentMethodType::from((int) $data['type']),
                trim((string) $data['reference']),
                (bool) ($data['is_active'] ?? true),
            );
            $this->organizations->attachPaymentMethod($organization, $paymentMethod->id);

            return $paymentMethod;
        });
    }
}
