<?php

namespace App\Repositories\Contact;

use App\Domain\Contact\Enums\PlatformType;
use App\Models\Contact;

class ContactRepository implements ContactRepositoryInterface
{
    public function firstOrCreate(PlatformType $platform, string $reference, string $name, bool $isActive): Contact
    {
        $contact = Contact::withTrashed()->firstOrCreate(
            ['platform' => $platform, 'reference' => $reference],
            ['name' => $name, 'is_active' => $isActive],
        );

        if ($contact->trashed()) {
            $contact->restore();
        }

        return $contact;
    }

    public function update(Contact $contact, array $attributes): Contact
    {
        $contact->update($attributes);

        return $contact;
    }
}
