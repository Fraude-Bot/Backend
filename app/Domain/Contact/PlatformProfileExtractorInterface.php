<?php

namespace App\Domain\Contact;

use App\Domain\Contact\Enums\PlatformType;

interface PlatformProfileExtractorInterface
{
    public function extract(PlatformType $type, string $url): string;
}
