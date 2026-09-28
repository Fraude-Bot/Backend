<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property string $path
 */
class ReportProof extends Model
{
    protected $fillable = [
        'report_id',
        'path',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
