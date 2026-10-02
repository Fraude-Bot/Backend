<?php

namespace App\Application\Organization\Commands;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Organization;

final readonly class HasOrganizationPaymentMethodCommand
{
    public function __construct(
        public Organization $organization,
        public PaymentMethodType $type,
        public string $reference,
    ) {}
}
