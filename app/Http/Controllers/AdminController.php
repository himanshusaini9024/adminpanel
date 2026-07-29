<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Settings;
use App\Models\User;
use App\Rules\MatchOldPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
class AdminController extends Controller
{
    public function index()
    {
        $data = User::select(
            DB::raw("COUNT(*) as count"),
            DB::raw("DAYNAME(created_at) as day_name"),
            DB::raw("DAY(created_at) as day")
        )
        ->where('created_at', '>', Carbon::today()->subDay(6))
        ->groupBy('day_name', 'day')
        ->orderBy('day')
        ->get();
        
        $array[] = ['Name', 'Number'];
        foreach ($data as $key => $value) {
            $array[++$key] = [$value->day_name, $value->count];
        }
        
        return view('backend.index')->with('users', json_encode($array));
    }

    public function profile()
    {
        $profile = Auth::user();
        return view('backend.users.profile')->with('profile', $profile);
    }

    public function profileUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'photo' => 'nullable|string|max:500'
        ]);
        
        try {
            $user = User::findOrFail($id);
            
            // Ensure user can only update their own profile unless admin
            if (Auth::id() != $id && Auth::user()->role != 'admin') {
                return redirect()->back()->with('error', 'Unauthorized action');
            }
            
            // Don't update role from profile page - role should be managed through user management
            $user->update($validated);
            
            return redirect()->back()
                ->with('success', 'Successfully updated your profile');
                
        } catch (\Exception $e) {
            \Log::error('Profile update failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Please try again!');
        }
    }

    public function settings()
    {
        $data = Settings::first();
        return view('backend.setting')->with('data', $data);
    }

    public function settingsUpdate(Request $request)
    {
        $validated = $request->validate([
            'short_des' => 'required|string|max:500',
            'description' => 'required|string',
            'photo' => 'required|string|max:500',
            'logo' => 'required|string|max:500',
            'address' => 'required|string|max:500',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
        ]);
        
        try {
            if (!empty($validated['photo'])) {
                $validated['photo'] = media_path($validated['photo']);
            }
            if (!empty($validated['logo'])) {
                $validated['logo'] = media_path($validated['logo']);
            }

            $settings = Settings::first();
            if (!$settings) {
                $settings = Settings::create($validated);
            } else {
                $settings->update($validated);
            }
            
            return redirect()->route('admin')
                ->with('success', 'Setting successfully updated');
                
        } catch (\Exception $e) {
            \Log::error('Settings update failed: ' . $e->getMessage());
            return redirect()->route('admin')
                ->with('error', 'Please try again');
        }
    }

    public function changePassword(){
        return view('backend.layouts.changePassword');
    }
    public function changPasswordStore(Request $request)
    {
        $request->validate([
            'current_password' => ['required', new MatchOldPassword],
            'new_password' => ['required'],
            'new_confirm_password' => ['same:new_password'],
        ]);

        User::find(auth()->user()->id)->update(['password'=> Hash::make($request->new_password)]);

        return redirect()->route('admin')->with('success','Password successfully changed');
    }

    // Pie chart
    public function userPieChart(Request $request)
    {
        $data = User::select(
            DB::raw("COUNT(*) as count"),
            DB::raw("DAYNAME(created_at) as day_name"),
            DB::raw("DAY(created_at) as day")
        )
        ->where('created_at', '>', Carbon::today()->subDay(6))
        ->groupBy('day_name', 'day')
        ->orderBy('day')
        ->get();
        
        $array[] = ['Name', 'Number'];
        foreach ($data as $key => $value) {
            $array[++$key] = [$value->day_name, $value->count];
        }
        
        return view('backend.index')->with('course', json_encode($array));
    }

    // public function activity(){
    //     return Activity::all();
    //     $activity= Activity::all();
    //     return view('backend.layouts.activity')->with('activities',$activity);
    // }

    public function storageLink(){
        // check if the storage folder already linked;
        if(File::exists(public_path('storage'))){
            // removed the existing symbolic link
            File::delete(public_path('storage'));

            //Regenerate the storage link folder
            try{
                Artisan::call('storage:link');
                request()->session()->flash('success', 'Successfully storage linked.');
                return redirect()->back();
            }
            catch(\Exception $exception){
                request()->session()->flash('error', $exception->getMessage());
                return redirect()->back();
            }
        }
        else{
            try{
                Artisan::call('storage:link');
                request()->session()->flash('success', 'Successfully storage linked.');
                return redirect()->back();
            }
            catch(\Exception $exception){
                request()->session()->flash('error', $exception->getMessage());
                return redirect()->back();
            }
        }
    }
}
