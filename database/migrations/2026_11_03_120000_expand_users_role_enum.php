<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','seo','marketing','sales','accounts','user') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // Move any new roles back to user before shrinking the enum
        DB::table('users')
            ->whereIn('role', ['seo', 'marketing', 'sales', 'accounts'])
            ->update(['role' => 'user']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user'");
    }
};
