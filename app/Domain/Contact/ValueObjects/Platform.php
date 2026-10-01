<?php

namespace App\Domain\Contact\ValueObjects;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Contact\PlatformProfileExtractorInterface;

class Platform
{
    public function __construct(private PlatformType $type) {}

    public function extractURL(string $url): string
    {
        return app(PlatformProfileExtractorInterface::class)->extract($this->type, $url);
    }
}
