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
        Schema::table('returns', function (Blueprint $table) {
            $table->enum('type', ['return', 'exchange'])
                  ->default('return')
                  ->after('status');

            $table->string('exchange_size')
                  ->nullable()
                  ->after('type');

          

            $table->unsignedBigInteger('replacement_order_id')
                  ->nullable()
                  ->after('exchange_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'exchange_size',
                'exchange_color',
                'replacement_order_id',
            ]);
        });
    }
};
