<?php

namespace App\Application\Report\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;

final readonly class StoreOrganizationReportCommand
{
    /**
     * @param  list<string>  $proofs
     * @param  list<string>  $productNames
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     * @param  list<OrganizationReportScammerInput>  $scammers
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $profilePicture,
        public array $proofs,
        public array $productNames,
        public string $organizationName,
        public array $contacts,
        public array $paymentMethods,
        public array $scammers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $contacts = self::contactsFrom($input['contacts'] ?? []);
        $paymentMethods = self::paymentMethodsFrom($input['payment_methods'] ?? []);
        $scammers = [];

        foreach ($input['scammers'] ?? [] as $scammer) {
            if (! is_array($scammer)) {
                continue;
            }

            $profilePicture = $scammer['profile_picture_path'] ?? null;

            $scammers[] = new OrganizationReportScammerInput(
                name: (string) ($scammer['name'] ?? ''),
                profilePicture: is_string($profilePicture) && $profilePicture !== '' ? $profilePicture : null,
                contacts: self::contactsFrom($scammer['contacts'] ?? []),
                paymentMethods: self::paymentMethodsFrom($scammer['payment_methods'] ?? []),
            );
        }

        $proofs = [];

        foreach ($input['proofs'] ?? [] as $url) {
            $proofs[] = is_string($url) ? $url : '';
        }

        $productNames = [];

        foreach ($input['products'] ?? [] as $name) {
            if (is_string($name) && $name !== '') {
                $productNames[] = $name;
            }
        }

        $organizationInput = is_array($input['organization'] ?? null) ? $input['organization'] : [];
        $profilePicture = $input['profile_picture'] ?? null;

        return new self(
            title: (string) ($input['title'] ?? ''),
            description: is_string($input['description'] ?? null) ? $input['description'] : null,
            profilePicture: is_string($profilePicture) && $profilePicture !== '' ? $profilePicture : null,
            proofs: $proofs,
            productNames: $productNames,
            organizationName: (string) ($organizationInput['name'] ?? ''),
            contacts: $contacts,
            paymentMethods: $paymentMethods,
            scammers: $scammers,
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

            $inputs[] = new ContactInput(
                platform: PlatformType::from((int) ($contact['platform'] ?? 0)),
                reference: (string) ($contact['reference'] ?? ''),
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

            $inputs[] = new PaymentMethodInput(
                type: PaymentMethodType::from((int) ($paymentMethod['type'] ?? 0)),
                reference: (string) ($paymentMethod['reference'] ?? ''),
            );
        }

        return $inputs;
    }
}
