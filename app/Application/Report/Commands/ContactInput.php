<?php

namespace App\Application\Report\Commands;

use App\Domain\Contact\Enums\PlatformType;

final readonly class ContactInput
{
    public function __construct(
        public string $name,
        public PlatformType $platform,
        public string $reference,
    ) {}
}
