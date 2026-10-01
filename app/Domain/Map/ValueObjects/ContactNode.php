<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\Enums\NodeTypes;

final class ContactNode extends Node
{
    public function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $contactId,
        public readonly string $label,
        public readonly string $detail,
        public readonly PlatformType $platform
    ) {}

    public static function fromContact(string $id, string $reference, PlatformType $platform): self
    {
        return new self(
            $id,
            NodeTypes::CONTACT,
            $id,
            ucfirst(strtolower($platform->name)),
            $reference,
            $platform,
        );
    }

    public function graphId(): string
    {
        return $this->type->value.':'.$this->contactId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->graphId(),
            'type' => $this->type->value,
            'contact_id' => $this->contactId,
            'label' => $this->label,
            'detail' => $this->detail,
            'platform' => ucfirst(strtolower($this->platform->name)),
        ];
    }
}
