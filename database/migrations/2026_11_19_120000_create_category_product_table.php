<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
            $table->index('category_id');
        });

        // Backfill from existing primary / child category columns
        $rows = DB::table('products')
            ->select('id', 'cat_id', 'child_cat_id')
            ->get();

        $now = now();
        $insert = [];
        foreach ($rows as $row) {
            foreach (array_unique(array_filter([(int) $row->cat_id, (int) $row->child_cat_id])) as $categoryId) {
                $insert[] = [
                    'product_id' => $row->id,
                    'category_id' => $categoryId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($insert) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('category_product')->insertOrIgnore($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
