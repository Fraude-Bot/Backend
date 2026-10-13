<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $type_name
 * @property Carbon $created_at
 * @property bool $is_active
 */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type_name,
            'created_at' => $this->created_at->format('Y-m-d'),
            'is_active' => $this->is_active,
        ];
    }
}
