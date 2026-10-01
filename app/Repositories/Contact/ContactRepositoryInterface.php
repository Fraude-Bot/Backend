<?php

namespace App\Repositories\Contact;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Contact;

interface ContactRepositoryInterface
{
    public function firstOrCreate(PlatformType $platform, string $reference, string $name, bool $isActive): Contact;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Contact $contact, array $attributes): Contact;
}
