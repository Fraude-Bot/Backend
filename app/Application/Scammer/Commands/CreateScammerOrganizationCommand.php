<?php

namespace App\Application\Scammer\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Scammer;

final readonly class CreateScammerOrganizationCommand
{
    /**
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     */
    public function __construct(
        public Scammer $scammer,
        public string $name,
        public ?string $profilePicturePath,
        public array $contacts,
        public array $paymentMethods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Scammer $scammer, array $data): self
    {
        $profilePicturePath = $data['profile_picture_path'] ?? null;

        return new self(
            scammer: $scammer,
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
