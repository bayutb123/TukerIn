<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    protected function create(RegisterRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $user = User::where('email', $validated['email'])->first();
            if ($user) {
                return response()->json([
                    'message' => 'Email already exists',
                    'user' => null
                ], 409);
            }
            $registered = User::forceCreate([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'api_token' => Str::random(80),
            ]);
            return response()->json([
                'message' => 'User created successfully',
                'user' => $registered,
            ], 201);
        }

        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }
}
