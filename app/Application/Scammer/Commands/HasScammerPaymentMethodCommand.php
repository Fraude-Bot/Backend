<?php

namespace App\Application\Scammer\Commands;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Scammer;

final readonly class HasScammerPaymentMethodCommand
{
    public function __construct(
        public Scammer $scammer,
        public PaymentMethodType $type,
        public string $reference,
    ) {}
}
