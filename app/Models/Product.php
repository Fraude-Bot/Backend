<?php

namespace App\Models;

use App\Domain\Product\Entities\ProductEntity;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, InvalidatesPublicCache;

    protected $fillable = [
        'category_id',
        'name',
        'emoji',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the reports associated with the product.
     */
    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'reports_products')
            ->using(ReportProduct::class)
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivotNull('deleted_at');
    }

    /**
     * Convert the model to a domain entity.
     */
    public function toEntity(): ProductEntity
    {
        return new ProductEntity(
            id: $this->id,
            categoryId: $this->category_id,
            name: $this->name,
            emoji: $this->emoji,
        );
    }
}
