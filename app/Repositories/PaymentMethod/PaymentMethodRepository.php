<?php

namespace App\Repositories\PaymentMethod;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Scammer;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function firstOrCreate(PaymentMethodType $type, string $reference, bool $isActive): PaymentMethod
    {
        $paymentMethod = PaymentMethod::withTrashed()->firstOrCreate(
            ['type' => $type, 'reference' => $reference],
            ['is_active' => $isActive],
        );

        if ($paymentMethod->trashed()) {
            $paymentMethod->restore();
        }

        return $paymentMethod;
    }

    public function linkedToScammer(Scammer $scammer, mixed $type, string $reference): bool
    {
        return $scammer->paymentMethods()->where([
            'reference' => $reference,
            'type' => $type,
        ])->exists();
    }

    public function linkedToOrganization(Organization $organization, mixed $type, string $reference): bool
    {
        return $organization->paymentMethods()->where([
            'reference' => $reference,
            'type' => $type,
        ])->exists();
    }
}
