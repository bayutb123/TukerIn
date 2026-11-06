<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    protected function login(LoginRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            if (!auth()->once($validated)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                    'user' => null
                ], 401);
            }
            $user = auth()->user();
            // users without a token can't call the authenticated endpoints
            if (!$user->api_token) {
                $user->forceFill(['api_token' => Str::random(80)])->save();
            }
            return response()->json([
                'message' => 'User logged in successfully',
                'user' => $user->makeVisible('api_token'),
            ], 200);
        }

        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }
}
