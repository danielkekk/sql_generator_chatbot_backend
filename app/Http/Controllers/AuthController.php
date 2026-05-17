<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $token = JWTAuth::attempt($credentials);

        try {
            if (!$token) {
                return response()->json(['error' => 'invalid_credentials'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        return response()->json([
            'token' => $token,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Successfully logged out']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_invalidate_token'], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json(compact('token'));
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'token_expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'could_not_refresh_token'], 500);
        }
    }
}
