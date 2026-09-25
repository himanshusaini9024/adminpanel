<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCouponRedemptionsAndOrderCouponCode extends Migration
{
    public function up()
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'max_uses_per_user')) {
                $table->unsignedInteger('max_uses_per_user')->default(1)->after('status');
            }
            if (!Schema::hasColumn('coupons', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('max_uses_per_user');
            }
        });

        if (!Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->timestamps();

                $table->unique(['coupon_id', 'customer_id']);
                $table->index('customer_id');
                $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code')->nullable()->after('coupon');
            }
        });

        // Seed / upsert one-time 5% coupon
        $exists = DB::table('coupons')->where('code', 'DHIRAGO5')->exists();
        if ($exists) {
            DB::table('coupons')->where('code', 'DHIRAGO5')->update([
                'type' => 'percent',
                'value' => 5,
                'status' => 'active',
                'max_uses_per_user' => 1,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('coupons')->insert([
                'code' => 'DHIRAGO5',
                'type' => 'percent',
                'value' => 5,
                'status' => 'active',
                'max_uses_per_user' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }
        });

        Schema::dropIfExists('coupon_redemptions');

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('coupons', 'max_uses_per_user')) {
                $table->dropColumn('max_uses_per_user');
            }
        });
    }
}
