<?php

namespace App\Application\Report\Commands;

final readonly class OrganizationReportScammerInput
{
    /**
     * @param  list<ContactInput>  $contacts
     * @param  list<PaymentMethodInput>  $paymentMethods
     */
    public function __construct(
        public string $name,
        public array $contacts,
        public array $paymentMethods,
    ) {}
}
