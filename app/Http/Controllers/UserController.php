<?php

namespace App\Http\Controllers;

use App\Models\Rating;
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

    protected function getUser($id) {
        $user = User::where('id', $id)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'user' => null
            ], 400);
        }
        $rating = Rating::where('user_id', $id)->get();
        // count rating points
        $ratingCount = count($rating);
        $trxPoint = 0;
        $ratingTotal = 0;
        foreach ($rating as $rate) {
            $trxPoint += $rate->points;
            $ratingTotal += $rate->rating;
        }
        if ($ratingCount > 0) {
            $ratingTotal = $ratingTotal / $ratingCount;
        } else {
            $ratingTotal = 0;
        }
        $user->rating = $ratingTotal;
        $user->trx_points = $trxPoint;
        return response()->json([
            'message' => 'User found',
            'user' => $user
        ], 200);
    }
}
