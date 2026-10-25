<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\EdgeKinds;
use JsonSerializable;

final class Edge implements JsonSerializable
{
    private function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $target,
        public readonly EdgeKinds $kind,
    ) {}

    public static function linked(int $sequence, Node $source, Node $target): self
    {
        return self::make($sequence, $source, $target, EdgeKinds::LINKED);
    }

    public static function contact(int $sequence, Node $source, Node $target): self
    {
        return self::make($sequence, $source, $target, EdgeKinds::CONTACT);
    }

    public static function payment(int $sequence, Node $source, Node $target): self
    {
        return self::make($sequence, $source, $target, EdgeKinds::PAYMENT);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'kind' => $this->kind->value,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function make(int $sequence, Node $source, Node $target, EdgeKinds $kind): self
    {
        return new self(
            'e'.$sequence,
            $source->graphId(),
            $target->graphId(),
            $kind,
        );
    }
}
