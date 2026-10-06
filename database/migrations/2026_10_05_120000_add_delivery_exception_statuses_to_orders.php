<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courier exceptions: failed delivery attempt (NDR), return to origin (RTO), lost/damaged.
 * Also adds out_for_delivery and refunded, which the code already writes.
 */
return new class extends Migration
{
    private const OLD = ['new', 'process', 'delivered', 'cancel', 'shipped', 'exchanged'];
    private const NEW = [
        'new', 'process', 'shipped', 'out_for_delivery', 'undelivered', 'delivered',
        'rto', 'rto_delivered', 'lost', 'cancel', 'exchanged', 'refunded',
    ];

    public function up(): void
    {
        $this->setStatusEnum(self::NEW);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_remark')->nullable()->after('shipping_status');
            $table->timestamp('rto_initiated_at')->nullable()->after('delivered_at');
            $table->timestamp('rto_delivered_at')->nullable()->after('rto_initiated_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_remark', 'rto_initiated_at', 'rto_delivered_at']);
        });

        DB::table('orders')->whereIn('status', ['out_for_delivery', 'undelivered', 'rto', 'lost'])->update(['status' => 'shipped']);
        DB::table('orders')->where('status', 'rto_delivered')->update(['status' => 'cancel']);
        DB::table('orders')->where('status', 'refunded')->update(['status' => 'delivered']);
        $this->setStatusEnum(self::OLD);
    }

    private function setStatusEnum(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
        DB::statement("ALTER TABLE orders MODIFY status ENUM({$list}) NOT NULL DEFAULT 'new'");
    }
};
