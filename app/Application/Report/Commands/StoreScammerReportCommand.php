<?php

namespace App\Application\Report\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;

final readonly class StoreScammerReportCommand
{
    /**
     * @param  list<string>  $proofs
     * @param  list<string>  $productNames
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     * @param  list<ScammerReportOrganizationInput>  $organizations
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $profilePicture,
        public array $proofs,
        public array $productNames,
        public string $scammerName,
        public array $contacts,
        public array $paymentMethods,
        public array $organizations,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $contacts = self::contactsFrom($input['contacts'] ?? []);
        $paymentMethods = self::paymentMethodsFrom($input['payment_methods'] ?? []);
        $organizations = [];

        foreach ($input['organizations'] ?? [] as $organization) {
            if (! is_array($organization)) {
                continue;
            }

            $profilePicture = $organization['profile_picture'] ?? null;

            $organizations[] = new ScammerReportOrganizationInput(
                name: (string) ($organization['name'] ?? ''),
                profilePicture: is_string($profilePicture) && $profilePicture !== '' ? $profilePicture : null,
                contacts: self::contactsFrom($organization['contacts'] ?? []),
                paymentMethods: self::paymentMethodsFrom($organization['payment_methods'] ?? []),
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

        $scammerInput = is_array($input['scammer'] ?? null) ? $input['scammer'] : [];
        $profilePicture = $input['profile_picture'] ?? null;

        return new self(
            title: (string) ($input['title'] ?? ''),
            description: is_string($input['description'] ?? null) ? $input['description'] : null,
            profilePicture: is_string($profilePicture) && $profilePicture !== '' ? $profilePicture : null,
            proofs: $proofs,
            productNames: $productNames,
            scammerName: (string) ($scammerInput['name'] ?? ''),
            contacts: $contacts,
            paymentMethods: $paymentMethods,
            organizations: $organizations,
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
