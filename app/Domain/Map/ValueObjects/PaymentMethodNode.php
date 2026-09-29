<?php

namespace App\Domain\Map\ValueObjects;

use App\Domain\Map\Enums\NodeTypes;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PaymentMethodNode extends Node{
    public function __construct(
        public readonly string $id,
        public readonly NodeTypes $type,
        public readonly string $paymentMethodId,
        public readonly string $label,
        public readonly string $detail,
    ) {}

    public static function from(Model $model): self {
        if (!$model instanceof PaymentMethod) {
            throw new \InvalidArgumentException('Model must be an instance of PaymentMethod');
        }

        return new self(
            (string) $model->id,
            NodeTypes::PAYMENT_METHOD,
            (string) $model->id,
            (string) $model->type->value,
            $model->reference,
        );
    }

    /**
     * @param  Collection<int, PaymentMethod>  $paymentMethods
     * @return Collection<int, PaymentMethodNode>
     */
    public static function fromCollection(Collection $paymentMethods): Collection
    {
        return $paymentMethods->map(fn (PaymentMethod $paymentMethod) => self::from($paymentMethod));
    }

    public function graphId(): string {
        return $this->type->value . ':' . $this->paymentMethodId;
    }

    public function toArray(): array {
        return [
            'id' => $this->graphId(),
            'type' => $this->type->value,
            'payment_method_id' => $this->paymentMethodId,
            'label' => $this->label,
            'detail' => $this->detail,
        ];
    }
}
