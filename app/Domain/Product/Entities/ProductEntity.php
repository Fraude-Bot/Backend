<?php

namespace App\Domain\Product\Entities;

use App\Domain\Entity;

class ProductEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $categoryId,
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
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'emoji' => $this->emoji,
        ];
    }
}
