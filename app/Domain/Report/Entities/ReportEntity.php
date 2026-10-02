<?php

namespace App\Domain\Report\Entities;

use App\Domain\Entity;

class ReportEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public string $title,
        public string $description,
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
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
