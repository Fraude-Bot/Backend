<?php

namespace App\Models;

use App\Domain\Map\ValueObjects\PaymentMethodNode;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, InvalidatesPublicCache, SoftDeletes;

    public const UPDATED_AT = 'modified_at';

    protected $fillable = [
        'type',
        'reference',
        'is_active',
    ];

    protected $casts = [
        'type' => PaymentMethodType::class,
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'type_name',
    ];

    protected function typeName(): Attribute
    {
        return Attribute::make(
            get: fn () => ucfirst(strtolower($this->type->name)),
        );
    }

    public function toNode(): PaymentMethodNode
    {
        return PaymentMethodNode::fromPaymentMethod((string) $this->id, (string) $this->type->value, $this->reference);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organizations_payment_methods')
            ->using(OrganizationPaymentMethod::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function scammers(): BelongsToMany
    {
        return $this->belongsToMany(Scammer::class, 'scammers_payment_methods')
            ->using(ScammerPaymentMethod::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }
}
