<?php

namespace App\Models;

use App\Domain\Contact\Entities\ContactEntity;
use App\Domain\Contact\Enums\PlatformType;
use App\Domain\Map\ValueObjects\ContactNode;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, InvalidatesPublicCache, SoftDeletes;

    protected $fillable = [
        'name',
        'platform',
        'reference',
        'is_active',
    ];

    protected $appends = [
        'platform_name',
    ];

    protected $casts = [
        'id' => 'integer',
        'reference' => 'string',
        'name' => 'string',
        'platform' => PlatformType::class,
        'is_active' => 'boolean',
    ];

    protected function platformName(): Attribute
    {
        return Attribute::make(
            get: fn () => ucfirst(strtolower($this->platform->name)),
        );
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organizations_contacts')
            ->using(OrganizationContact::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function scammers(): BelongsToMany
    {
        return $this->belongsToMany(Scammer::class, 'scammers_contacts')
            ->using(ScammerContact::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function toNode(): ContactNode
    {
        return ContactNode::fromContact((string) $this->id, $this->reference, $this->platform);
    }

    public function toEntity(): ContactEntity
    {
        return new ContactEntity(
            id: $this->id,
            name: $this->name,
            platformType: $this->platform,
            reference: $this->reference,
            isActive: $this->is_active,
        );
    }
}
