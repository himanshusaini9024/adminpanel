<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->string('module', 50);
            $table->timestamps();

            $table->unique(['role', 'module']);
            $table->index('role');
        });

        $defaults = config('roles.permissions', []);
        $now = now();
        $rows = [];
        foreach ($defaults as $role => $modules) {
            foreach ($modules as $module) {
                $rows[] = [
                    'role' => $role,
                    'module' => $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if ($rows) {
            DB::table('role_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
