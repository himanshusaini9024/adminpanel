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
      // database/migrations/xxxx_create_returns_table.php

Schema::create('returns', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('order_id');
    $table->unsignedBigInteger('user_id');

    $table->string('reason');
    $table->text('comment')->nullable();

    $table->enum('status', [
        'pending',
        'approved',
        'rejected',
        'pickup_scheduled',
        'picked_up',
        'in_transit',
        'delivered',
        'refunded'
    ])->default('pending');

    $table->string('reverse_awb')->nullable();
    $table->string('courier')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
