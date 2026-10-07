<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property int $report_count
 * @property Collection $reports
 * @property bool $is_active
 * @property Carbon $created_at
 * @property string|null $profile_picture_path
 */
class OrganizationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reports' => $this->report_count,
            'profile_picture' => $this->profile_picture_path,
            'products' => $this->reports->flatMap(fn ($report) => $report->products->pluck('name'))->filter()->unique()->values()->all(),
            'status' => $this->is_active,
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
