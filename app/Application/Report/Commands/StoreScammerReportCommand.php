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
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $contacts = [];

        foreach ($input['contacts'] ?? [] as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $contacts[] = new ContactInput(
                name: (string) ($contact['name'] ?? ''),
                platform: PlatformType::from((int) ($contact['platform'] ?? 0)),
                reference: (string) ($contact['reference'] ?? ''),
            );
        }

        $paymentMethods = [];

        foreach ($input['payment_methods'] ?? [] as $paymentMethod) {
            if (! is_array($paymentMethod)) {
                continue;
            }

            $paymentMethods[] = new PaymentMethodInput(
                type: PaymentMethodType::from((int) ($paymentMethod['type'] ?? 0)),
                reference: (string) ($paymentMethod['reference'] ?? ''),
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
        );
    }
}
