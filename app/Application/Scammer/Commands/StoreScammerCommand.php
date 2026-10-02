<?php

namespace App\Application\Scammer\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;

final readonly class StoreScammerCommand
{
    /**
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     */
    public function __construct(
        public string $name,
        public bool $isActive,
        public array $contacts,
        public array $paymentMethods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $contacts = [];

        foreach ($data['contacts'] ?? [] as $contactData) {
            if (! is_array($contactData)) {
                continue;
            }

            $contacts[] = new ContactInput(
                name: (string) $contactData['name'],
                platform: PlatformType::from((int) $contactData['platform']),
                reference: (string) $contactData['reference'],
                isActive: (bool) ($contactData['is_active'] ?? true),
            );
        }

        $paymentMethods = [];

        foreach ($data['paymentMethods'] ?? [] as $paymentMethodData) {
            if (! is_array($paymentMethodData)) {
                continue;
            }

            $paymentMethods[] = new PaymentMethodInput(
                type: PaymentMethodType::from((int) $paymentMethodData['type']),
                reference: (string) $paymentMethodData['reference'],
                isActive: (bool) ($paymentMethodData['is_active'] ?? true),
            );
        }

        return new self(
            name: (string) $data['name'],
            isActive: (bool) ($data['is_active'] ?? true),
            contacts: $contacts,
            paymentMethods: $paymentMethods,
        );
    }
}
