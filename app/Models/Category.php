<?php

namespace App\Models;

use App\Domain\Category\Entities\CategoryEntity;
use App\Models\Concerns\InvalidatesPublicCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, InvalidatesPublicCache;

    protected $fillable = [
        'name',
        'emoji',
    ];

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Convert the model to a domain entity.
     */
    public function toEntity(): CategoryEntity
    {
        return new CategoryEntity(
            id: $this->id,
            name: $this->name,
            emoji: $this->emoji,
        );
    }
}
