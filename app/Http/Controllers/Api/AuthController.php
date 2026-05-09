<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;

class AuthController extends Controller
{
    //
    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10'
        ]);

        $otp = rand(100000, 999999);

        // Save OTP in DB
        DB::table('otps')->updateOrInsert(
            ['mobile' => $request->mobile],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // 🔥 Fast2SMS cURL

        // $fields = [
        //     "route" => "otp",
        //     "variables_values" => "$otp",
        //     "numbers" => $request->mobile,
        // ];
        $fields = [
            "route" => "q",   // ✅ IMPORTANT CHANGE
            "message"  => "Your One-Time Password (OTP) for login is $otp. This code is valid for five minutes. For your security, please do not share this OTP with anyone.",
            "numbers" => $request->mobile,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                "authorization: " . env('FAST2SMS_API_KEY'),
                "accept: application/json",
                "content-type: application/json"
            ],
        ]);

        $response = curl_exec($curl);

        $result = json_decode($response, true);
        return response()->json([
            'raw' => $response,
            'decoded' => $result
        ]);
        if (curl_errno($curl)) {
            return response()->json([
                'message' => 'SMS failed',
                'error' => curl_error($curl)
            ], 500);
        }

        curl_close($curl);

        return response()->json([
            'message' => 'OTP sent successfully',
            // 🔥 show OTP only in local
            'otp' => app()->environment('local') ? $otp : null
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|digits:10',
            'otp' => 'required'
        ]);

        $otpData = DB::table('otps')
            ->where('mobile', $request->mobile)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpData) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (now()->gt($otpData->expires_at)) {
            return response()->json(['message' => 'OTP expired'], 400);
        }



        try {
            $user = Customer::firstOrCreate(
                [
                    'phone' => $request->mobile,
                ]
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
        // $user->tokens()->delete();
        // $token = $user->createToken('auth_token')->plainTextToken;
        Auth::guard('customer')->login($user);
        // delete OTP after use
        DB::table('otps')->where('mobile', $request->mobile)->delete();

        return response()->json([
            // 'token' => $token,
            'user' => $user
        ]);
    }

    public function firebaseLogin(Request $request)
    {
        $phone = $request->phone;
        $uid = $request->uid;

        $user = Customer::where('phone', $phone)->first();

        if (!$user) {
            $user = Customer::create([
                'phone' => $phone,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = Customer::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }
        Auth::guard('customer')->login($user);

        // $user->tokens()->delete();

        // $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user
        ]);
    }



    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out'])
            ->cookie('e_commerce_session', '', -1, '/', config('session.domain'), false, true)  // ✅ expire session cookie
            ->cookie('XSRF-TOKEN', '', -1, '/', config('session.domain'), false, false);         // ✅ expire XSRF cookie
    }
}
