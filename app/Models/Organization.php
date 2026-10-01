<?php

namespace App\Models;

use App\Domain\Map\ValueObjects\OrganizationNode;
use App\Domain\Organization\OrganizationEntity;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
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

    protected static function booted()
    {
        static::deleted(function (Organization $organization) {
            OrganizationContact::query()->where('organization_id', $organization->id)->delete();
            OrganizationPaymentMethod::query()->where('organization_id', $organization->id)->delete();
            OrganizationReport::query()->where('organization_id', $organization->id)->delete();
        });

        static::restoring(function (Organization $organization) {
            OrganizationContact::onlyTrashed()->where('organization_id', $organization->id)->restore();
            OrganizationPaymentMethod::onlyTrashed()->where('organization_id', $organization->id)->restore();
            OrganizationReport::onlyTrashed()->where('organization_id', $organization->id)->restore();
        });
    }

    public function reportCount(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->attributes['reports_count']
                ?? ($this->relationLoaded('reports') ? $this->reports->count() : 0)),
        );
    }

    /**
     * Get the scammers associated with the organization.
     */
    public function scammers(): BelongsToMany
    {
        return $this->belongsToMany(Scammer::class, 'scammers_organizations');
    }

    /**
     * Get the contacts associated with the organization.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'organizations_contacts')
            ->using(OrganizationContact::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the reports associated with the organization.
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'organizations_reports')
            ->using(OrganizationReport::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Get the payment methods associated with the organization.
     */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'organizations_payment_methods')
            ->using(OrganizationPaymentMethod::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Convert the model to a domain entity.
     */
    public function toNode(): OrganizationNode
    {
        return OrganizationNode::fromOrganization((string) $this->id, $this->name);
    }

    public function toEntity(): OrganizationEntity
    {
        return new OrganizationEntity(
            id: $this->id,
            name: $this->name,
            isActive: $this->is_active,
        );
    }
}
