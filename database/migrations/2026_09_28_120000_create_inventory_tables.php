<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateInventoryTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_stocks')) {
            Schema::create('product_stocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                // '' = product without sizes (one size)
                $table->string('size', 20)->default('');
                $table->integer('stock')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'size']);
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('size', 20)->default('');
                $table->integer('change');
                $table->integer('stock_after');
                $table->string('reason', 40);
                $table->string('reference_type', 40)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'stock_deducted_at')) {
                $table->timestamp('stock_deducted_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'stock_restored_at')) {
                $table->timestamp('stock_restored_at')->nullable();
            }
        });

        Schema::table('returns', function (Blueprint $table) {
            if (!Schema::hasColumn('returns', 'restocked_at')) {
                $table->timestamp('restocked_at')->nullable();
            }
        });

        $this->backfillFromProducts();
    }

    /**
     * Seed per-size rows from the old single `stock` value so nothing goes
     * out of stock on deploy. Each listed size gets the old product stock.
     */
    private function backfillFromProducts(): void
    {
        $now = now();

        DB::table('products')->select('id', 'size', 'stock')->orderBy('id')->chunk(200, function ($products) use ($now) {
            foreach ($products as $product) {
                if (DB::table('product_stocks')->where('product_id', $product->id)->exists()) {
                    continue;
                }

                $sizes = array_values(array_unique(array_filter(array_map(
                    fn ($s) => strtoupper(trim($s)),
                    explode(',', (string) $product->size)
                ), fn ($s) => $s !== '')));

                if ($sizes === []) {
                    $sizes = [''];
                }

                $perSize = max(0, (int) $product->stock);
                $rows = [];
                foreach ($sizes as $size) {
                    $rows[] = [
                        'product_id' => $product->id,
                        'size' => $size,
                        'stock' => $perSize,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('product_stocks')->insert($rows);

                DB::table('products')->where('id', $product->id)->update([
                    'stock' => $perSize * count($sizes),
                ]);
            }
        });
    }

    public function down()
    {
        Schema::table('returns', function (Blueprint $table) {
            if (Schema::hasColumn('returns', 'restocked_at')) {
                $table->dropColumn('restocked_at');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['stock_deducted_at', 'stock_restored_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('product_stocks');
    }
}
