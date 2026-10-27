<?php

namespace App\Application\Organization\Commands;

use App\Application\Scammer\Commands\ContactInput;
use App\Application\Scammer\Commands\PaymentMethodInput;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Organization;

final readonly class CreateOrganizationScammerCommand
{
    /**
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     */
    public function __construct(
        public Organization $organization,
        public string $name,
        public ?string $profilePicturePath,
        public array $contacts,
        public array $paymentMethods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Organization $organization, array $data): self
    {
        $profilePicturePath = $data['profile_picture_path'] ?? null;

        return new self(
            organization: $organization,
            name: trim((string) $data['name']),
            profilePicturePath: is_string($profilePicturePath) ? $profilePicturePath : null,
            contacts: self::contactsFrom($data['contacts'] ?? []),
            paymentMethods: self::paymentMethodsFrom($data['payment_methods'] ?? []),
        );
    }

    /**
     * @return list<ContactInput>
     */
    private static function contactsFrom(mixed $contacts): array
    {
        if (! is_array($contacts)) {
            return [];
        }

        $inputs = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $reference = (string) ($contact['reference'] ?? '');
            $inputs[] = new ContactInput(
                platform: PlatformType::fromReference($reference),
                reference: $reference,
                isActive: true,
            );
        }

        return $inputs;
    }

    /**
     * @return list<PaymentMethodInput>
     */
    private static function paymentMethodsFrom(mixed $paymentMethods): array
    {
        if (! is_array($paymentMethods)) {
            return [];
        }

        $inputs = [];

        foreach ($paymentMethods as $paymentMethod) {
            if (! is_array($paymentMethod)) {
                continue;
            }

            $reference = (string) ($paymentMethod['reference'] ?? '');
            $inputs[] = new PaymentMethodInput(
                type: PaymentMethodType::fromReference($reference),
                reference: $reference,
                isActive: true,
            );
        }

        return $inputs;
    }
}
