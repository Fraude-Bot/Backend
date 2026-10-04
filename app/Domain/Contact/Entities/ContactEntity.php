<?php

namespace App\Domain\Contact\Entities;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Contact\ValueObjects\Platform;
use App\Domain\Entity;

class ContactEntity extends Entity
{
    public function __construct(
        public readonly ?int $id,
        public PlatformType $platformType,
        public string $reference,
        public bool $isActive,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platformType,
            'reference' => $this->reference,
            'is_active' => $this->isActive,
        ];
    }

    protected function validate(): void
    {
        if ($this->reference === '') {
            throw new \InvalidArgumentException('Reference cannot be empty');
        }

        if (strlen($this->reference) > 255) {
            throw new \InvalidArgumentException('Reference cannot exceed 255 characters');
        }
    }

    protected function transform(): void
    {
        $this->reference = trim($this->reference);

        if ($this->platformType === PlatformType::CELLPHONE) {
            $this->reference = preg_replace('/\D+/', '', $this->reference) ?? '';
        }

        if (filter_var($this->reference, FILTER_VALIDATE_URL) || preg_match('/^(?!:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/i', $this->reference) || preg_match('/^(http|https):\/\/[^ "]+$/i', $this->reference)) {
            $socialMediaVO = new Platform($this->platformType);

            $this->reference = $socialMediaVO->extractURL($this->reference);
        }
    }
}
