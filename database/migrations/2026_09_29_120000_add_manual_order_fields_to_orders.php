<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','paypal','online','bank_transfer') NOT NULL DEFAULT 'cod'");
        // WhatsApp customers don't always share an email.
        DB::statement("ALTER TABLE orders MODIFY email VARCHAR(191) NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_source', 20)->default('website')->after('order_type');
            $table->string('payment_reference', 100)->nullable()->after('razorpay_payment_id');
            $table->string('payment_proof')->nullable()->after('payment_reference');
            $table->timestamp('paid_at')->nullable()->after('payment_proof');
            $table->text('admin_note')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_source', 'payment_reference', 'payment_proof', 'paid_at', 'admin_note']);
        });

        DB::statement("UPDATE orders SET email = '' WHERE email IS NULL");
        DB::statement("ALTER TABLE orders MODIFY email VARCHAR(191) NOT NULL");
        DB::statement("UPDATE orders SET payment_method = 'online' WHERE payment_method = 'bank_transfer'");
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cod','paypal','online') NOT NULL DEFAULT 'cod'");
    }
};
