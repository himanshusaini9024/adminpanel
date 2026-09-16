<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'special_price')) {
                $table->decimal('special_price', 12, 2)->nullable()->after('price');
            }
        });

        // Default: 15% off MRP for every product that has no special_price yet.
        DB::table('products')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->where(function ($q) {
                $q->whereNull('special_price')->orWhere('special_price', '<=', 0);
            })
            ->update([
                'discount' => DB::raw('CASE WHEN discount IS NULL OR discount <= 0 THEN 15 ELSE discount END'),
                'special_price' => DB::raw('ROUND(price * (1 - (CASE WHEN discount IS NULL OR discount <= 0 THEN 15 ELSE discount END) / 100), 2)'),
            ]);

        // Sync discount from special_price when special_price already existed somehow
        DB::statement('
            UPDATE products
            SET discount = ROUND(((price - special_price) / price) * 100, 2)
            WHERE price > 0
              AND special_price IS NOT NULL
              AND special_price > 0
              AND special_price < price
              AND (discount IS NULL OR discount <= 0)
        ');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'special_price')) {
                $table->dropColumn('special_price');
            }
        });
    }
};
