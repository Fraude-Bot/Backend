<?php

namespace App\Application\Scammer\Commands;

use App\Domain\Contact\Enums\PlatformType;

final readonly class ContactInput
{
    public function __construct(
        public PlatformType $platform,
        public string $reference,
        public bool $isActive,
    ) {}
}
