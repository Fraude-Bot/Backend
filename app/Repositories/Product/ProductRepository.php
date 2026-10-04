<?php

namespace App\Repositories\Product;

use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    public function firstOrCreate(string $name): Product
    {
        $existing = $this->findByName($name);

        if ($existing instanceof Product) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): Product => Product::query()->create(['name' => $name]));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findByName($name) ?? throw $exception;
        }
    }

    private function findByName(string $name): ?Product
    {
        return Product::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->first();
    }
}
