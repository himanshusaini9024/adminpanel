<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'photo_mobile')) {
                $table->string('photo_mobile')->nullable()->after('photo');
            }
        });

        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'photo_mobile')) {
                $table->longText('photo_mobile')->nullable()->after('photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'photo_mobile')) {
                $table->dropColumn('photo_mobile');
            }
        });

        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'photo_mobile')) {
                $table->dropColumn('photo_mobile');
            }
        });
    }
};
