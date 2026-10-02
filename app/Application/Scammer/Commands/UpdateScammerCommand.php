<?php

namespace App\Application\Scammer\Commands;

use App\Models\Scammer;

final readonly class UpdateScammerCommand
{
    public function __construct(
        public Scammer $scammer,
        public ?string $name = null,
        public bool $nameProvided = false,
        public ?bool $isActive = null,
        public bool $isActiveProvided = false,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromValidated(Scammer $scammer, array $attributes): self
    {
        return new self(
            scammer: $scammer,
            name: array_key_exists('name', $attributes) ? (string) $attributes['name'] : null,
            nameProvided: array_key_exists('name', $attributes),
            isActive: array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : null,
            isActiveProvided: array_key_exists('is_active', $attributes),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = [];

        if ($this->nameProvided) {
            $attributes['name'] = $this->name;
        }

        if ($this->isActiveProvided) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
