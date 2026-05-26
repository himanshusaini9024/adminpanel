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
            //
             $table->string('reverse_order_id')
                ->nullable()
                ->after('reverse_awb');

            $table->string('reverse_shipment_id')
                ->nullable()
                ->after('reverse_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            //

            $table->dropColumn([
                'reverse_order_id',
                'reverse_shipment_id'
            ]);
        });
    }
};
