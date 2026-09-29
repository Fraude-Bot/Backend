<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\NodeTypes;
use App\Domain\Map\Enums\KindTypes;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class OrganizationNode extends Node {
    private function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $partyId,
        public readonly string $name,
        public readonly KindTypes $kind,
        public readonly bool $isCenter = false,
    ) {}

    public static function from(Model $model): self {
        if (!$model instanceof Organization) {
            throw new \InvalidArgumentException('Model must be an instance of Organization');
        }

        return new self(
            (string) $model->id,
            NodeTypes::PARTY,
            (string) $model->id,
            $model->name,
            KindTypes::ORGANIZATION,
        );
    }

    public static function fromCollection(Collection $organizations): Collection
    {
        return $organizations->map(fn (Organization $organization) => self::from($organization));
    }

    public function centered(): self
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
        return $this->type->value . ':' . $this->kind->value . ':' . $this->id;
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
