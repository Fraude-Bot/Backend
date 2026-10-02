<?php

namespace App\Application\Scammer\Commands;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Scammer;

final readonly class CreateScammerContactCommand
{
    public function __construct(
        public Scammer $scammer,
        public string $name,
        public PlatformType $platform,
        public string $reference,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(Scammer $scammer, array $data): self
    {
        return new self(
            scammer: $scammer,
            name: (string) $data['name'],
            platform: PlatformType::from((int) $data['platform']),
            reference: (string) $data['reference'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
