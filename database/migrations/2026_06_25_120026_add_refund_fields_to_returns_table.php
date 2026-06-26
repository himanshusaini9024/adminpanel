<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('returns', function ($table) {

            $table->string('refund_id')->nullable();

            $table->decimal(
                'refund_amount',
                10,
                2
            )->nullable();

            $table->timestamp(
                'refunded_at'
            )->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            //
        });
    }
};
