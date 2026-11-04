<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('cart_updated_at')->nullable()->after('cart');
            $table->timestamp('cart_reminder_sent_at')->nullable()->after('cart_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['cart_updated_at', 'cart_reminder_sent_at']);
        });
    }
};
