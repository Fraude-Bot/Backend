<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\Enums\NodeTypes;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ContactNode extends Node {
    public function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $contactId,
        public readonly string $label,
        public readonly string $detail,
        public readonly PlatformType $platform
    ) {}

    public function graphId(): string {
        return $this->type->value . ':' . $this->contactId;
    }

    public static function from(Model $model): self {
        if (!$model instanceof Contact) {
            throw new \InvalidArgumentException('Model must be an instance of Contact');
        }

        return new self(
            (string) $model->id,
            NodeTypes::CONTACT,
            (string) $model->id,
            ucfirst(strtolower($model->platform->name)),
            $model->reference,
            $model->platform,
        );
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return Collection<int, ContactNode>
     */
    public static function fromCollection(Collection $contacts): Collection
    {
        return $contacts->map(fn (Contact $contact) => self::from($contact));
    }

    public function toArray(): array {
        return [
            'id' => $this->graphId(),
            'type' => $this->type->value,
            'contact_id' => $this->contactId,
            'label' => $this->label,
            'detail' => $this->detail,
            'platform' => ucfirst(strtolower($this->platform->name)),
        ];
    }
}
