<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RolePermission extends Model
{
    protected $fillable = ['role', 'module'];

    public static function cacheKey(string $role): string
    {
        return 'role_permissions:' . $role;
    }

    public static function clearCache(?string $role = null): void
    {
        if ($role) {
            Cache::forget(self::cacheKey($role));
            return;
        }

        foreach (array_keys(config('roles.labels', [])) as $r) {
            Cache::forget(self::cacheKey($r));
        }
    }

    /**
     * @return list<string>
     */
    public static function modulesForRole(string $role): array
    {
        if ($role === 'admin') {
            return ['*'];
        }

        return Cache::remember(self::cacheKey($role), 3600, function () use ($role) {
            $modules = static::where('role', $role)->orderBy('module')->pluck('module')->all();

            // Fallback to config defaults if DB empty for this role
            if (!$modules) {
                $modules = config('roles.permissions.' . $role, []);
            }

            return array_values($modules);
        });
    }

    /**
     * Replace all modules for a role.
     *
     * @param  list<string>  $modules
     */
    public static function syncRole(string $role, array $modules): void
    {
        if ($role === 'admin') {
            return;
        }

        $allowed = array_keys(config('roles.modules', []));
        $modules = array_values(array_unique(array_intersect($modules, $allowed)));

        // Always keep dashboard + profile so the user can open admin and change password
        foreach (['dashboard', 'profile'] as $required) {
            if (!in_array($required, $modules, true) && in_array($required, $allowed, true)) {
                $modules[] = $required;
            }
        }

        static::where('role', $role)->delete();

        $now = now();
        $rows = array_map(fn ($module) => [
            'role' => $role,
            'module' => $module,
            'created_at' => $now,
            'updated_at' => $now,
        ], $modules);

        if ($rows) {
            static::insert($rows);
        }

        self::clearCache($role);
    }
}
