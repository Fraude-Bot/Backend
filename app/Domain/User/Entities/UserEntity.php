<?php

namespace App\Domain\User\Entities;

use App\Domain\Entity;

class UserEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public string $email,
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
            'email' => $this->email,
        ];
    }
}
