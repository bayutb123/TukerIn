<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\ReviewPostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\PostImage;
use App\Http\Requests\UpdatePostRequest;
use App\Models\User;
use App\Models\Review;
use App\Models\PostCategory;
use GuzzleHttp\Client;

class PostController extends Controller
{
    protected function create(CreatePostRequest $request) {
        $validated = $request->validated();
        if ($validated) {
            // replace quoets with empty string on title and desc
            $validated['title'] = str_replace('"', '', $validated['title']);
            $validated['content'] = str_replace('"', '', $validated['content']);
            $validated['type'] = str_replace('"', '', $validated['type']);
            $post = Post::create([
                'user_id' => $validated['user_id'],
                'title' => $validated['title'],
                'content' => $validated['content'],
                'type' => $validated['type'],
                'status' => "active",
                'price' => $validated['price'],
                'is_premium' => 0,
                'can_trade_in' => $validated['can_trade_in'],
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

    protected function searchPost($query, $id, $limit = 10) {
        $posts = Post::where('title', 'LIKE', "%{$query}%")->where('user_id', '!=', $id)->where('is_published', 1)->paginate(10);
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }

        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total()
            ]
        ], 200);
    }

    protected function searchSuggestion($query, $id) {
        // select max 10 posts with unique title and is_published
        $posts = Post::where('title', 'LIKE', "%{$query}%")->where('user_id', '!=', $id)->where('is_published', 1)->get();

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
        $posts = Post::where('user_id', $user_id)->orderBy('created_at', 'desc')->paginate($limit);
        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }

        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total()
            ]
        ], 200);
    }

    protected function getPosts($user_id, $limit = 10) {
        // get all posts except user's posts abd only published posts
        $_posts = Post::where('user_id', '!=', $user_id)->where('is_published', '>', 0)->where('is_published', '<', 3)->orderBy('created_at', 'desc')->paginate($limit);
        foreach ($_posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }
        // premium post are shown first
        $premium_posts = $_posts->where('is_premium', 1)->sortByDesc('id');

        // standard post are shown after premium posts and ordered by id by desc
        $standard_posts = $_posts->where('is_premium', 0)->sortByDesc('id');

        // combine premium and all posts
        $posts = $premium_posts->merge($standard_posts);

        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts,
            'pagination' => [
                'current_page' => $_posts->currentPage(),
                'last_page' => $_posts->lastPage(),
                'per_page' => $_posts->perPage(),
                'total' => $_posts->total()
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

    protected function getPostCategories() {
        $categories = PostCategory::all();
        if (sizeof($categories) == 0) {
            return response()->json([
                'message' => 'Categories not found',
                'categories' => $categories
            ], 200);
        }

        return response()->json([
            'message' => 'Categories found',
            'categories' => $categories
        ], 200);
    }

    protected function getPostSubCategories($id) {
        $categories = PostCategory::where('parent_id', $id)->get();
        if (sizeof($categories) == 0) {
            return response()->json([
                'message' => 'Categories not found',
                'categories' => $categories
            ], 200);
        }

        return response()->json([
            'message' => 'Categories found',
            'categories' => $categories
        ], 200);
    }

    protected function createReview(ReviewPostRequest $request) {
        $validated = $request->validated();
        $post = Post::where('id', $validated['post_id'])->first();
        $post_owner = $post->user_id;
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        } elseif ($post->status == 'reviewed') {
            return response()->json([
                'message' => 'Post already reviewed',
            ], 409);
        }
        $validated['post_owner_id'] = $post_owner;
        if ($validated) {
            $review = Review::create(
                [
                    'user_id' => $validated['user_id'],
                    'post_id' => $validated['post_id'],
                    'post_owner_id' => $validated['post_owner_id'],
                    'review' => $validated['review'],
                    'rating' => $validated['rating'],
                    'point' => $post->price * 1 / 100
                ]);
            $post->status = 'reviewed';
            $post->is_published = 0;
            $post->save();
            return response()->json([
                'message' => 'Review created successfully',
                'review' => $review
            ], 201);
        }
        return response()->json([
            'message' => 'Invalid request',
            'errors' => $validated->errors()
        ], 400);
    }

    protected function getPostReviews($id) {
        $reviews = Review::where('post_id', $id)->get();
        if (sizeof($reviews) == 0) {
            return response()->json([
                'message' => 'Reviews not found',
                'reviews' => $reviews
            ], 200);
        }
        foreach ($reviews as $review) {
            $review->user = User::where('id', $review->user_id)->first();
            $review->post = Post::where('id', $review->post_id)->first();
            $review->post_owner = User::where('id', $review->post_owner_id)->first();
        }

        return response()->json([
            'message' => 'Reviews found',
            'reviews' => $reviews
        ], 200);
    }

    protected function getUserRating($id) {
        $reviews = Review::where('post_owner_id', $id)->get();
        $points = 0;
        $rating = 0;
        $count = sizeof($reviews);
        if (sizeof($reviews) == 0) {
            return response()->json([
                'message' => 'Rating not found',
                'count' => $count,
                'rating' => $rating,
                'points' => $points
            ], 200);
        }
        foreach ($reviews as $review) {
            $review->post = Post::where('id', $review->post_id)->first();
            $points += $review->point;
            $rating += $review->rating / sizeof($reviews);
        }


        return response()->json([
            'message' => 'Rating found',
            'count' => $count,
            'rating' => $rating,
            'points' => $points
        ], 200);
    }

    protected function changePublishStatus(Request $request) {
        $validated = $request->validate([
            'post_id' => 'required|integer',
            'peer_id' => 'required|integer',
            'publish_status_id' => 'required|integer'
        ]);
        $post = Post::where('id', $validated['post_id'])->first();
        if (!$post) {
            return response()->json([
                'message' => 'Post not found',
            ], 404);
        }
        // replace "" from is_published and peer_id
        $post->is_published = str_replace('"', '', $validated['publish_status_id']);
        $post->peer_id = str_replace('"', '', $validated['peer_id']);
        $post->save();
        return response()->json([
            'message' => 'Post set active successfully',
            'post' => $post
        ], 200);
    }

    protected function getActivePosts($peer_id) {
        $posts = Post::where('peer_id', $peer_id)->where('is_published', '>', 1)->get();
        if (sizeof($posts) == 0) {
            return response()->json([
                'message' => 'Posts not found',
                'posts' => $posts
            ], 200);
        }

        foreach ($posts as $post) {
            $post->thumnail = PostImage::where('post_id', $post->id)->first();
            $post->author = User::where('id', $post->user_id)->first();
        }

        return response()->json([
            'message' => 'Posts found',
            'posts' => $posts
        ], 200);
    }

}
