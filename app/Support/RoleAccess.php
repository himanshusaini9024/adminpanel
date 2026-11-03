<?php

namespace App\Support;

use App\Models\User;

class RoleAccess
{
    public static function staffRoles(): array
    {
        return array_values(config('roles.staff', ['admin']));
    }

    public static function labels(): array
    {
        return config('roles.labels', []);
    }

    public static function isStaff(?User $user): bool
    {
        return $user && in_array($user->role, self::staffRoles(), true);
    }

    public static function isAdmin(?User $user): bool
    {
        return $user && $user->role === 'admin';
    }

    public static function modulesFor(?User $user): array
    {
        if (!$user) {
            return [];
        }

        if ($user->role === 'admin') {
            return ['*'];
        }

        return \App\Models\RolePermission::modulesForRole($user->role);
    }

    public static function canModule(?User $user, string $module): bool
    {
        if (!$user || !self::isStaff($user)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return in_array($module, self::modulesFor($user), true);
    }

    public static function moduleForRoute(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        $map = config('roles.route_modules', []);

        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        $best = null;
        $bestLen = -1;
        foreach ($map as $prefix => $module) {
            $matches = $routeName === $prefix
                || str_starts_with($routeName, rtrim($prefix, '.') . '.')
                || (str_ends_with($prefix, '.') && str_starts_with($routeName, $prefix));

            if ($matches && strlen($prefix) > $bestLen) {
                $best = $module;
                $bestLen = strlen($prefix);
            }
        }

        return $best;
    }

    public static function canAccessRoute(?User $user, ?string $routeName): bool
    {
        if (!self::isStaff($user)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $module = self::moduleForRoute($routeName);
        if ($module === null) {
            // Unknown admin route: allow dashboard-like access only for safety? Deny.
            return false;
        }

        return self::canModule($user, $module);
    }
}
