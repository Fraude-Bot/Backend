<?php

namespace App\Domain\Organization\Entities;

use App\Domain\Entity;

class OrganizationEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public bool $isActive,
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
            'is_active' => $this->isActive,
        ];
    }
}
