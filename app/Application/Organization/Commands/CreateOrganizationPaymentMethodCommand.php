<?php

namespace App\Application\Organization\Commands;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Organization;

final readonly class CreateOrganizationPaymentMethodCommand
{
    public function __construct(
        public Organization $organization,
        public PaymentMethodType $type,
        public string $reference,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Organization $organization, array $data): self
    {
        return new self(
            organization: $organization,
            type: PaymentMethodType::from((int) $data['type']),
            reference: (string) $data['reference'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
