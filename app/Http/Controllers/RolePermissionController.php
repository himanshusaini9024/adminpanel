<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Support\RoleAccess;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $labels = RoleAccess::labels();
        $modules = config('roles.modules', []);
        $permissions = [];

        foreach (array_keys($labels) as $role) {
            if ($role === 'admin') {
                $permissions[$role] = array_keys($modules);
                continue;
            }
            $permissions[$role] = RolePermission::modulesForRole($role);
        }

        return view('backend.roles.index', compact('labels', 'modules', 'permissions'));
    }

    public function update(Request $request, string $role)
    {
        $labels = RoleAccess::labels();
        if (!isset($labels[$role]) || $role === 'admin') {
            return redirect()->route('roles.index')
                ->with('error', $role === 'admin'
                    ? 'Admin always has full access and cannot be changed.'
                    : 'Unknown role.');
        }

        $allowedModules = array_keys(config('roles.modules', []));
        $data = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'string|in:' . implode(',', $allowedModules),
        ]);

        RolePermission::syncRole($role, $data['modules'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', ($labels[$role] ?? $role) . ' access updated.');
    }
}
