<?php

namespace App\Application\Scammer\Commands;

final readonly class StoreScammerCommand
{
    public function __construct(
        public string $name,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
