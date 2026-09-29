<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\NodeTypes;
use App\Domain\Map\Enums\KindTypes;
use App\Models\Scammer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ScammerNode extends Node {
    private function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $partyId,
        public readonly string $name,
        public readonly KindTypes $kind,
        public readonly bool $isCenter = false,
    ) {}

    public static function from(Model $model): self {
        if (!$model instanceof Scammer) {
            throw new \InvalidArgumentException('Model must be an instance of Scammer');
        }

        return new self(
            (string) $model->id,
            NodeTypes::PARTY,
            (string) $model->id,
            $model->name,
            KindTypes::SCAMMER,
        );
    }

    /**
     * @param  Collection<int, Scammer>  $scammers
     * @return Collection<int, ScammerNode>
     */
    public static function fromCollection(Collection $scammers): Collection
    {
        return $scammers->map(fn (Scammer $scammer) => self::from($scammer));
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
