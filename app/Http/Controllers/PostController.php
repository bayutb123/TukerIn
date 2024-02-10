<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\PostImage;
use App\Http\Requests\UpdatePostRequest;
use App\Models\User;
use GuzzleHttp\Client;

class PostController extends Controller
{
    protected function create(CreatePostRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            // replace quoets with empty string on title and desc
            $validated['title'] = str_replace('"', '', $validated['title']);
            $validated['content'] = str_replace('"', '', $validated['content']);
            $post = Post::create([
                'user_id' => $validated['user_id'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'status' => 0,
                'price' => $validated['price'],
                'is_premium' => 0,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'city' => $this->getAddressFromLatLong($validated['latitude'], $validated['longitude'])->original['address']
            ]);

            if (is_array($request->image)) {
                $arrayIndex = 0;
                foreach ($request->image as $image) {
                    $imageName = $post->id.time().'_' . $arrayIndex++.'.'.$image->extension();
                    $image->move(public_path('images'), $imageName);
                    $image = PostImage::create([
                        'post_id' => $post->id,
                        'image_name' => $imageName
                    ]);
                }
            } else {
                $imageName = time().'.'.$request->image->extension();
                $request->image->move(public_path('images'), $imageName);
                $image = PostImage::create([
                    'post_id' => $post->id,
                    'image_name' => $imageName
                ]);
            }

            return response()->json([
                'message' => 'Post created successfully',
                'post' => $post,
                'first_image' => $image
            ], 201);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }

    protected function uploadImage(Request $request) {
        $validated = $request->validate([
            'post_id' => 'required|integer',
            'image.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);
        $uploaded = [];
        if ($validated) {
            if (is_array($request->image)) {
                $arrayIndex = 0;
                foreach ($request->image as $image) {
                    $imageName = $request['post_id'].time().'_' . $arrayIndex++.'.'.$image->extension();
                    $image->move(public_path('images'), $imageName);
                    $image = PostImage::create([
                        'post_id' => $validated['post_id'],
                        'image_name' => $imageName
                    ]);
                    array_push($uploaded, $image);
                }
            } else {
                $imageName = time().'.'.$request->image->extension();
                $request->image->move(public_path('images/post'), $imageName);
                $image = PostImage::create([
                    'post_id' => $validated['post_id'],
                    'image_name' => $imageName
                ]);
                array_push($uploaded, $image);
            }

            return response()->json([
                'message' => 'Image uploaded successfully',
                'image' => $uploaded
            ], 201);
        }
        return response()->json([
            'message' => 'Invalid request',
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
        // get all images of post just image_name
        $images = PostImage::where('post_id', $post->id)->get();
        $post->address = $post->city;
        $post->images = $images->pluck('image_name');
        $post->author_name = $author->name;
        $post->author_email = $author->email;
        return response()->json([
            'message' => 'Post found',
            'post' => $post,
        ], 200);
    }

    protected function getMyPosts($user_id, $limit = 10) {
        $posts = Post::where('user_id', "==", $user_id)->orderBy('created_at', 'desc')->paginate($limit);
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        

        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total()
            ]
        ], 200);
    }

    protected function getPosts($user_id, $limit = 10) {
        $posts = Post::where('user_id', "!=", $user_id)->orderBy('created_at', 'desc')->paginate($limit);
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        // premium post are shown first
        $premium_posts = $posts->where('is_premium', 1)->sortByDesc('id');

        // standard post are shown after premium posts and ordered by id by desc
        $standard_posts = $posts->where('is_premium', 0)->sortByDesc('id');

        // combine premium and all posts
        $all_posts = $premium_posts->merge($standard_posts);

        return response()->json([
            'message' => 'Posts found',
            'posts' => $all_posts,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total()
            ]
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

    protected function getAddressFromLatLong($lat, $long) {
        $url = "https://api.geoapify.com/v1/geocode/reverse?lat=".$lat."&lon=".$long."&apiKey=".config('app.geoapify_api_key');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        $address = json_decode($response);
        curl_close($ch);

        $properties = $address->features[0]->properties;

        return response()->json([
            'address' => $properties->city
        ], 200);
    }

}
