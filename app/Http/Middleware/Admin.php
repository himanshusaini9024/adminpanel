<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;

class Admin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !RoleAccess::isStaff($user)) {
            $request->session()->flash('error', 'You do not have permission to access the admin panel.');
            return redirect()->route('home');
        }

        if ($user->status === 'inactive') {
            auth()->logout();
            $request->session()->flash('error', 'Your account is inactive. Contact an administrator.');
            return redirect()->route('login');
        }

        $routeName = optional($request->route())->getName();

        // Prefer named routes; fall back to path heuristics for unnamed admin routes.
        $allowed = $routeName
            ? RoleAccess::canAccessRoute($user, $routeName)
            : $this->allowUnnamedPath($user, $request->path());

        if (!$allowed) {
            $request->session()->flash('error', 'You do not have permission to access this page.');
            return redirect()->route('admin');
        }

        return $next($request);
    }

    private function allowUnnamedPath($user, string $path): bool
    {
        if (RoleAccess::isAdmin($user)) {
            return true;
        }

        $path = trim($path, '/');
        $guesses = [
            'admin/returns' => 'returns',
            'admin/refund' => 'returns',
            'admin/exchange' => 'returns',
            'admin/cloudinary' => 'file_manager',
            'admin/s3-' => 'file_manager',
        ];

        foreach ($guesses as $prefix => $module) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix)) {
                return RoleAccess::canModule($user, $module);
            }
        }

        // Unknown unnamed route: deny for non-admins
        return false;
    }
}
