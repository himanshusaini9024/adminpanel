<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('push_tokens', 'p256dh')) {
                $table->string('p256dh', 255)->nullable()->after('token');
            }
            if (!Schema::hasColumn('push_tokens', 'auth')) {
                $table->string('auth', 255)->nullable()->after('p256dh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('push_tokens', 'auth')) {
                $table->dropColumn('auth');
            }
            if (Schema::hasColumn('push_tokens', 'p256dh')) {
                $table->dropColumn('p256dh');
            }
        });
    }
};
