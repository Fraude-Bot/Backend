<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\NodeTypes;

final class PaymentMethodNode extends Node
{
    public function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $paymentMethodId,
        public readonly string $label,
        public readonly string $detail,
    ) {}

    public static function fromPaymentMethod(string $id, string $type, string $reference): self
    {
        return new self(
            $id,
            NodeTypes::PAYMENT_METHOD,
            $id,
            $type,
            $reference,
        );
    }

    public function graphId(): string
    {
        return $this->type->value.':'.$this->paymentMethodId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->graphId(),
            'type' => $this->type->value,
            'payment_method_id' => $this->paymentMethodId,
            'label' => $this->label,
            'detail' => $this->detail,
        ];
    }
}
