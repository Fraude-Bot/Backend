<?php

namespace App\Application\Organization\Commands;

use App\Models\Organization;

final readonly class AddOrganizationScammerCommand
{
    public function __construct(
        public Organization $organization,
        public int $scammerId,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromValidated(Organization $organization, array $attributes): self
    {
        return new self(
            organization: $organization,
            scammerId: (int) $attributes['scammer_id'],
        );
    }
}
