<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreatePendingCheckoutsTable extends Migration
{
    public function up()
    {
        Schema::create('pending_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('razorpay_order_id', 64)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 10, 2);
            // Full order payload from the checkout page, used to create the order if the
            // browser never gets back to us after payment.
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->string('razorpay_payment_id', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
        });

        $duplicates = DB::table('orders')
            ->whereNotNull('razorpay_order_id')
            ->groupBy('razorpay_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('razorpay_order_id');

        if ($duplicates->isEmpty()) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('razorpay_order_id', 'orders_razorpay_order_id_unique');
            });
        } else {
            Log::warning('orders.razorpay_order_id unique index skipped: duplicates exist', [
                'razorpay_order_ids' => $duplicates->all(),
            ]);
        }
    }

    public function down()
    {
        if (collect(DB::select("SHOW INDEX FROM orders WHERE Key_name = 'orders_razorpay_order_id_unique'"))->isNotEmpty()) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_razorpay_order_id_unique');
            });
        }

        Schema::dropIfExists('pending_checkouts');
    }
}
