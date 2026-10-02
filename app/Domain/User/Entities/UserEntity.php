<?php

namespace App\Domain\User\Entities;

use App\Domain\Entity;

class UserEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public string $username,
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
            'username' => $this->username,
            'email' => $this->email,
        ];
    }
}
