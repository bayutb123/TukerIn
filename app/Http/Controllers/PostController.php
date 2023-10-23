<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostController extends Controller
{
    protected function uploadImage(Request $request) {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);
        if ($validated) {
            $imageName = time().'.'.$request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            return response()->json([
                'message' => 'Image uploaded successfully',
                'image' => $imageName
            ], 200);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }
}
