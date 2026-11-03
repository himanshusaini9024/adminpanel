<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use App\Support\RoleAccess;

class UsersController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', RoleAccess::staffRoles())
            ->orderBy('id', 'ASC')
            ->paginate(10);

        return view('backend.users.index')->with('users', $users);
    }

    public function create()
    {
        return view('backend.users.create', [
            'roles' => RoleAccess::labels(),
        ]);
    }

    public function store(Request $request)
    {
        $staffRoles = implode(',', RoleAccess::staffRoles());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:' . $staffRoles,
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|string|max:500',
        ]);

        try {
            $validated['password'] = Hash::make($validated['password']);
            User::create($validated);

            return redirect()->route('users.index')
                ->with('success', 'Successfully added user');
        } catch (\Exception $e) {
            \Log::error('User creation failed: ' . $e->getMessage());
            return redirect()->route('users.index')
                ->with('error', 'Error occurred while adding user');
        }
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);

        return view('backend.users.edit', [
            'user' => $user,
            'roles' => RoleAccess::labels(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $staffRoles = implode(',', RoleAccess::staffRoles());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'role' => 'required|in:' . $staffRoles,
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        try {
            if ($request->filled('password')) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Do not demote the last remaining admin
            if ($user->role === 'admin' && $validated['role'] !== 'admin') {
                $otherAdmins = User::where('role', 'admin')->where('id', '!=', $user->id)->count();
                if ($otherAdmins === 0) {
                    return redirect()->route('users.index')
                        ->with('error', 'You cannot remove the last admin account.');
                }
            }

            $status = $user->update($validated);

            return redirect()->route('users.index')
                ->with($status ? 'success' : 'error',
                    $status ? 'Successfully updated' : 'Error occurred while updating');
        } catch (\Exception $e) {
            \Log::error('User update failed: ' . $e->getMessage());
            return redirect()->route('users.index')
                ->with('error', 'Error occurred while updating');
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->id === auth()->id()) {
                return redirect()->route('users.index')
                    ->with('error', 'You cannot delete your own account');
            }

            if ($user->role === 'admin') {
                $otherAdmins = User::where('role', 'admin')->where('id', '!=', $user->id)->count();
                if ($otherAdmins === 0) {
                    return redirect()->route('users.index')
                        ->with('error', 'You cannot delete the last admin account.');
                }
            }

            $status = $user->delete();

            return redirect()->route('users.index')
                ->with($status ? 'success' : 'error',
                    $status ? 'User successfully deleted' : 'There is an error while deleting user');
        } catch (\Exception $e) {
            \Log::error('User deletion failed: ' . $e->getMessage());
            return redirect()->route('users.index')
                ->with('error', 'There is an error while deleting user');
        }
    }
}
