<?php

namespace App\Application\Scammer\Commands;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;

final readonly class PaymentMethodInput
{
    public function __construct(
        public PaymentMethodType $type,
        public string $reference,
        public bool $isActive,
    ) {}
}
