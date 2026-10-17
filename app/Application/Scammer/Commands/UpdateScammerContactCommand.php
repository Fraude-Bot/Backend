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
    public static function fromInput(Scammer $scammer, Contact $contact, array $input): self
    {
        $referenceProvided = array_key_exists('reference', $input);
        $reference = $referenceProvided ? (string) $input['reference'] : null;

        return new self(
            scammer: $scammer,
            contact: $contact,
            platform: $referenceProvided ? PlatformType::fromReference((string) $reference) : null,
            platformProvided: $referenceProvided,
            reference: $reference,
            referenceProvided: $referenceProvided,
            isActive: array_key_exists('is_active', $input) ? (bool) $input['is_active'] : null,
            isActiveProvided: array_key_exists('is_active', $input),
        );
    }
}
