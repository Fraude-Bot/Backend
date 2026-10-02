<?php

namespace App\Application\Organization\Commands;

final readonly class CreateOrganizationCommand
{
    public function __construct(
        public string $name,
        public ?bool $isActive = null,
        public bool $isActiveProvided = false,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromValidated(array $attributes): self
    {
        return new self(
            name: (string) $attributes['name'],
            isActive: array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : null,
            isActiveProvided: array_key_exists('is_active', $attributes),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = ['name' => $this->name];

        if ($this->isActiveProvided) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
