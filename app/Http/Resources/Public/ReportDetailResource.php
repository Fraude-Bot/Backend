<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $title
 * @property string|null $description
 * @property bool $is_active
 * @property Collection $proofs
 * @property Collection $products
 * @property Carbon $created_at
 */
class ReportDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'proofs' => $this->proofs->pluck('path')->all(),
            'products' => $this->products->pluck('name')->all(),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
