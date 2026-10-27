<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailToCustomerAddressesTable extends Migration
{
    public function up()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_addresses', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });
    }

    public function down()
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('customer_addresses', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
}
