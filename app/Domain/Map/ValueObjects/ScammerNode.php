<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\KindTypes;
use App\Domain\Map\Enums\NodeTypes;

final class ScammerNode extends Node
{
    private function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $partyId,
        public readonly string $name,
        public readonly KindTypes $kind,
        public readonly bool $isCenter = false,
    ) {}

    public static function fromScammer(string $id, string $name): self
    {
        return new self(
            $id,
            NodeTypes::PARTY,
            $id,
            $name,
            KindTypes::SCAMMER,
        );
    }

    public function center(): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->partyId,
            $this->name,
            $this->kind,
            true,
        );
    }

    public function graphId(): string
    {
        return $this->type->value.':'.$this->kind->value.':'.$this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->graphId(),
            'type' => $this->type->value,
            'party_id' => $this->partyId,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'is_center' => $this->isCenter,
        ];
    }
}
