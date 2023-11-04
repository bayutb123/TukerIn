<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SavePostRequest;
use App\Models\SavedPost;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;

class SavedPostController extends Controller
{
    protected function savePost(SavePostRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $check = SavedPost::where('post_id', $validated['post_id'])->where('user_id', $validated['user_id'])->first();
            if ($check) {
                return response()->json([
                    'message' => 'Post already saved, please check your saved post',
                ], 409);
            }

            $savedPost = SavedPost::create($validated);
            return response()->json([
                'message' => 'Post saved successfully',
            ], 200);
        }

        return response()->json([
            'message' => 'Invalid request'
        ], 400);
    }

    protected function deleteSavedPost($user_id, $post_id) {
        $savedPost = SavedPost::where('user_id', $user_id)->where('post_id', $post_id)->first();
        if (!$savedPost) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }
        $savedPost->delete();
        return response()->json([
            'message' => 'Post unsaved successfully',
        ], 200);
    }

    protected function getSavedPosts($user_id) {
        $savedPosts = SavedPost::where('user_id', $user_id)->get();
        $posts = [];
        foreach ($savedPosts as $savedPost) {
            $post = Post::where('id', $savedPost->post_id)->first();
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
            array_push($posts, $post);
        }
        return response()->json([
            'message' => 'Saved posts found',
            'posts' => $posts
        ], 200);
    }
}
