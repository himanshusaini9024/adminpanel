<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users=User::orderBy('id','ASC')->paginate(10);
        return view('backend.users.index')->with('users',$users);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.users.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,user',
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|string|max:500',
        ]);

        try {
            $validated['password'] = Hash::make($validated['password']);
            $user = User::create($validated);
            
            return redirect()->route('users.index')
                ->with('success', 'Successfully added user');
        } catch (\Exception $e) {
            \Log::error('User creation failed: ' . $e->getMessage());
            return redirect()->route('users.index')
                ->with('error', 'Error occurred while adding user');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user=User::findOrFail($id);
        return view('backend.users.edit')->with('user',$user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'role' => 'required|in:admin,user',
            'status' => 'required|in:active,inactive',
            'photo' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        try {
            // Only update password if provided
            if ($request->filled('password')) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting own account
            if ($user->id === auth()->id()) {
                return redirect()->route('users.index')
                    ->with('error', 'You cannot delete your own account');
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
