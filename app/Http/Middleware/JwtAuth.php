<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuth
{

    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 401);
            }

            auth()->setUser($user);
            $request->setUserResolver(fn () => $user);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        return $next($request);
    }
}
