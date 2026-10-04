<?php

namespace App\Application\Report\Commands;

final readonly class ScammerReportOrganizationInput
{
    /**
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     */
    public function __construct(
        public string $name,
        public ?string $profilePicture,
        public array $contacts,
        public array $paymentMethods,
    ) {}
}
