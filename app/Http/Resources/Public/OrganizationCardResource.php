<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\Public\Concerns\ResolvesCardPreviewLists;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int $report_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OrganizationCardResource extends JsonResource
{
    use ResolvesCardPreviewLists;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'reports' => $this->report_count,
            'products' => $this->previewProductNames(),
            'type' => 'organization',
            'created_at' => $this->created_at->format('Y-m-d'),
            'updated_at' => $this->updated_at->format('Y-m-d'),
        ];
    }
}
