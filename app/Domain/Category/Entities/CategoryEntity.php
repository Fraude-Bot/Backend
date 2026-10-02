<?php

namespace App\Domain\Category\Entities;

use App\Domain\Entity;

class CategoryEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public string $emoji,
    ) {
        parent::__construct();
    }

    protected function transform(): void
    {
        // To be implemented
    }

    protected function validate(): void
    {
        // To be implemented
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'emoji' => $this->emoji,
        ];
    }
}
