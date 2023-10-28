<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;

class UserController extends Controller
{
    protected function update(UpdateUserRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $user = User::where('id', $validated['id'])->first();
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                    'user' => null
                ], 400);
            }
            if ($validated['photo_path']) {
                $user->profile_photo_path = $validated['photo_path'];
            }
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->save();
            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
            ], 200);
        }

        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }
}
