<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\PostImage;
use App\Http\Requests\UpdatePostRequest;
use App\Models\User;
use App\Http\Requests\SavePostRequest;
use App\Models\SavedPost;

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

    protected function searchPost($query, $id) {
        $posts = Post::where('title', 'LIKE', "%{$query}%")->where('user_id', '!=', $id)->get();
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts
        ], 200);
    }

    protected function searchSuggestion($query, $id) {
        // select max 10 posts with unique title
        $posts = Post::where('title', 'LIKE', "%{$query}%")->where('user_id', '!=', $id)->get();
        $posts = $posts->unique('title');
        $suggestions = [];
        foreach ($posts as $post) {
            // push only title and id
            array_push($suggestions, [
                'title' => $post->title
            ]);
        }
        $suggestions = array_slice($suggestions, 0, 10);
        return response()->json([
            'suggestions' => $suggestions
        ], 200);
    }

    protected function getPost($id) {
        $post = Post::where('id', $id)->first();
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
                'post' => null
            ], 400);
        }
        $author = User::where('id', $post->user_id)->first();
        $post->images = PostImage::where('post_id', $post->id)->get();
        $post->author = $author;
        return response()->json([
            'message' => 'Post found',
            'post' => $post,
        ], 200);
    }

    protected function getPosts($user_id) {
        $posts = Post::where('user_id', '!=', $user_id)->get();
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        // premium post are shown first
        $premium_posts = $posts->where('is_premium', 1);

        // standard post are shown after premium posts and ordered by id by desc
        $standard_posts = $posts->where('is_premium', 0)->sortByDesc('id');

        // combine premium and all posts
        $all_posts = $premium_posts->merge($standard_posts);
        return response()->json([
            'message' => 'Posts found',
            'posts' => $all_posts
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

    protected function updatePost(UpdatePostRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            $post = Post::where('id', $validated['post_id'])->first();
            if (!$post) {
                return response()->json([
                    'message' => 'Post not found',
                    'post' => null
                ], 400);
            }
            $post->title = $validated['title'];
            $post->content = $validated['content'] ? $validated['content'] : $post->content;
            if ($validated['status']) {
                $post->status = $validated['status'];
            }
            $post->is_premium = $validated['is_premium'] ? $validated['is_premium'] : $post->is_premium;
            $post->latitude = $validated['latitude'] ? $validated['latitude'] : $post->latitude;
            $post->longitude = $validated['longitude'] ? $validated['longitude'] : $post->longitude;
            $post->save();
            return response()->json([
                'message' => 'Post updated successfully',
                'post' => $post
            ], 200);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }

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
