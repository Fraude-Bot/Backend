<?php

namespace App\Application\Scammer\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Contact;
use App\Models\Scammer;

final readonly class UpdateScammerContactCommand
{
    public function __construct(
        public Scammer $scammer,
        public Contact $contact,
        public ?PlatformType $platform = null,
        public bool $platformProvided = false,
        public ?string $reference = null,
        public bool $referenceProvided = false,
        public ?bool $isActive = null,
        public bool $isActiveProvided = false,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(Scammer $scammer, Contact $contact, array $input, bool $platformProvided): self
    {
        return new self(
            scammer: $scammer,
            contact: $contact,
            platform: $platformProvided ? PlatformType::from((int) $input['platform']) : null,
            platformProvided: $platformProvided,
            reference: array_key_exists('reference', $input) ? (string) $input['reference'] : null,
            referenceProvided: array_key_exists('reference', $input),
            isActive: array_key_exists('is_active', $input) ? (bool) $input['is_active'] : null,
            isActiveProvided: array_key_exists('is_active', $input),
        );
    }
}
