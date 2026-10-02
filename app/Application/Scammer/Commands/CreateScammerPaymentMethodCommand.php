<?php

namespace App\Application\Scammer\Commands;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Scammer;

final readonly class CreateScammerPaymentMethodCommand
{
    public function __construct(
        public Scammer $scammer,
        public PaymentMethodType $type,
        public string $reference,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Scammer $scammer, array $data): self
    {
        return new self(
            scammer: $scammer,
            type: PaymentMethodType::from((int) $data['type']),
            reference: (string) $data['reference'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
