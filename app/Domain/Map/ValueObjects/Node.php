<?php

namespace App\Domain\Map\ValueObjects;

use JsonSerializable;

abstract class Node implements JsonSerializable
{
    abstract public function toArray(): array;

    abstract public function graphId(): string;

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
