<?php

namespace App\Models;

use App\Domain\Map\ValueObjects\ScammerNode;
use App\Domain\Scammer\ScammerEntity;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property string|null $profile_picture_path
 * @property bool $is_active
 */
class Scammer extends Model
{
    use HasFactory, InvalidatesPublicCache, SoftDeletes;

    protected $fillable = [
        'name',
        'profile_picture_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'report_count',
    ];

    protected function reportCount(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->attributes['reports_count']
                ?? ($this->relationLoaded('reports') ? $this->reports->count() : 0)),
        );
    }

    protected static function booted()
    {
        static::deleted(function (Scammer $scammer) {
            ScammerContact::query()->where('scammer_id', $scammer->id)->delete();
            ScammerPaymentMethod::query()->where('scammer_id', $scammer->id)->delete();
            ScammerReport::query()->where('scammer_id', $scammer->id)->delete();
        });

        static::restoring(function (Scammer $scammer) {
            ScammerContact::onlyTrashed()->where('scammer_id', $scammer->id)->restore();
            ScammerPaymentMethod::onlyTrashed()->where('scammer_id', $scammer->id)->restore();
            ScammerReport::onlyTrashed()->where('scammer_id', $scammer->id)->restore();
        });
    }

    /**
     * Get the contacts associated with the scammer.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'scammers_contacts')
            ->using(ScammerContact::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the payment methods associated with the scammer.
     */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'scammers_payment_methods')
            ->using(ScammerPaymentMethod::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the organizations associated with the scammer.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'scammers_organizations', 'scammer_id', 'organization_id');
    }

    /**
     * Get the reports associated with the scammer.
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'scammers_reports')
            ->using(ScammerReport::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Convert the model to a domain entity.
     */
    public function toNode(): ScammerNode
    {
        return ScammerNode::fromScammer((string) $this->id, $this->name);
    }

    public function toEntity(): ScammerEntity
    {
        return new ScammerEntity(
            id: $this->id,
            name: $this->name,
            profilePicturePath: $this->profile_picture_path,
            isActive: $this->is_active,
        );
    }
}
