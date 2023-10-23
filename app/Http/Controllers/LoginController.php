<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    protected function login(LoginRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $user = auth()->attempt($validated);
            if (!$user) {
                return response()->json([
                    'message' => 'Invalid credentials',
                    'user' => null
                ], 400);
            }
            return response()->json([
                'message' => 'User logged in successfully',
                'user' => auth()->user(),
            ], 200);
        }

        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }
}
