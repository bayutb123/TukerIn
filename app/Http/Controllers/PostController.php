<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\PostImage;
use App\Models\User;

class PostController extends Controller
{
    protected function create(CreatePostRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $post = Post::create($validated);
            $images = $validated['image'];
            foreach ($images as $image) {
                $imagePost = PostImage::create([
                    'post_id' => $post->id,
                    'image_name' => $image
                ]);
            }
            $post->image_id = $imagePost->post_id;
            return response()->json([
                'message' => 'Post created successfully',
                'post' => $post
            ], 200);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }

    protected function uploadImage(Request $request) {
        $validated = $request->validate([
            'post_id' => 'required|integer',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);
        if ($validated) {
            $imageName = time().'.'.$request->image->extension();
            $request->image->move(public_path('images'), $imageName);
            return response()->json([
                'message' => 'Image uploaded successfully',
                'image_name' => $imageName
            ], 200);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }

    protected function getPost($id) {
        $post = Post::where('id', $id)->first();
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'post' => null
            ], 400);
        }
        $post->images = PostImage::where('post_id', $post->id)->get();
        return response()->json([
            'message' => 'Post found',
            'post' => $post
        ], 200);
    }

    protected function getPosts() {
        $posts = Post::all();
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        $posts = $posts->sortByDesc('is_premium');
        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts
        ], 200);
    }

    protected function deletePost($id) {
        $post = Post::where('id', $id)->first();
        $images = PostImage::where('post_id', $post->id)->get();
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'post' => null
            ], 400);
        }
        foreach ($images as $image) {
            $image->delete();
        }
        $post->delete();
        return response()->json([
            'message' => 'Post deleted successfully',
            'post' => $post
        ], 200);
    }
}