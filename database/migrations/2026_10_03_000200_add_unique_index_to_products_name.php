<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateProductNames();

        Schema::table('products', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    private function deduplicateProductNames(): void
    {
        $groups = [];

        foreach (DB::table('products')->orderBy('id')->get(['id', 'name']) as $product) {
            $groups[mb_strtolower((string) $product->name)][] = $product;
        }

        foreach ($groups as $products) {
            if (count($products) < 2) {
                continue;
            }

            $canonical = array_shift($products);

            foreach ($products as $duplicate) {
                $links = DB::table('reports_products')->where('product_id', $duplicate->id)->get();

                foreach ($links as $link) {
                    $existing = DB::table('reports_products')
                        ->where('report_id', $link->report_id)
                        ->where('product_id', $canonical->id)
                        ->first();

                    if ($existing === null) {
                        $values = (array) $link;
                        unset($values['id']);
                        $values['product_id'] = $canonical->id;
                        DB::table('reports_products')->insert($values);

                        continue;
                    }

                    if ($existing->deleted_at !== null && $link->deleted_at === null) {
                        DB::table('reports_products')->where('id', $existing->id)->update([
                            'deleted_at' => null,
                            'updated_at' => $link->updated_at,
                        ]);
                    }
                }

                DB::table('reports_products')->where('product_id', $duplicate->id)->delete();
                DB::table('products')->where('id', $duplicate->id)->delete();
            }
        }
    }
};
