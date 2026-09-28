<?php

namespace App\Models;

use App\Domain\Report\ReportEntity;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, InvalidatesPublicCache, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Report $report) {
            ReportProduct::query()->where('report_id', $report->id)->delete();
        });

        static::restoring(function (Report $report) {
            ReportProduct::onlyTrashed()->where('report_id', $report->id)->restore();
        });
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ReportProof::class);
    }

    /**
     * Get the products associated with the report.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'reports_products')
            ->using(ReportProduct::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the user that owns the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organizations_reports')
            ->using(OrganizationReport::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function scammers(): BelongsToMany
    {
        return $this->belongsToMany(Scammer::class, 'scammers_reports')
            ->using(ScammerReport::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Convert the model to a domain entity.
     */
    public function toEntity(): ReportEntity
    {
        return new ReportEntity(
            id: $this->id,
            userId: $this->user_id,
            title: $this->title,
            description: $this->description,
            isActive: $this->is_active,
        );
    }
}
