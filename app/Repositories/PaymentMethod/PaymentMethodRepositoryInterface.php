<?php

namespace App\Repositories\PaymentMethod;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;

interface PaymentMethodRepositoryInterface
{
    public function firstOrCreate(PaymentMethodType $type, string $reference, bool $isActive): PaymentMethod;

    public function linkedToScammer(Scammer $scammer, mixed $type, string $reference): bool;

    public function linkedToOrganization(Organization $organization, mixed $type, string $reference): bool;
}
