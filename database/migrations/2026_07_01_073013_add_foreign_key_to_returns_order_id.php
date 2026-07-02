<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('returns', function (Blueprint $table) {
        $table->foreign('order_id')
              ->references('id')
              ->on('orders')
              ->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns_order_id', function (Blueprint $table) {
            //
        });
    }
};
