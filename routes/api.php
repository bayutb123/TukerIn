<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\ChatController;


Route::post('/user/register', [RegisterController::class, 'create']);
Route::post('/user/login', [LoginController::class, 'login']);
Route::post('/user/update', [UserController::class, 'update']);
Route::get('/user/get/{id}', [UserController::class, 'getUser']);
Route::get('/user/rating/{id}', [PostController::class, 'getUserRating']);

Route::post('/post/create', [PostController::class, 'create']);
Route::post('/post/uploadImage', [PostController::class, 'uploadImage']);
Route::get('/post/all/{userId}', [PostController::class, 'getPosts']);
Route::get('/my/post/all/{id}', [PostController::class, 'getMyPosts']);
Route::get('/post/get/{id}', [PostController::class, 'getPost']);
Route::get('/post/search/{query}/{id}', [PostController::class, 'searchPost']);
Route::get('/post/search/suggestion/{query}/{id}', [PostController::class, 'searchSuggestion']);
Route::put('/post/update', [PostController::class, 'updatePost']);
Route::delete('/post/delete/{id}', [PostController::class, 'deletePost']);
Route::get('/post/review/{id}', [PostController::class, 'getPostReviews']);
Route::post('/post/review', [PostController::class, 'createReview']);
Route::get('/post/active/{id}', [PostController::class, 'getActivePosts']);
Route::post('/post/update/publish/status', [PostController::class, 'changePublishStatus']);

Route::post('/post/save', [SavedPostController::class, 'savePost']);
Route::get('/post/saved/{id}', [SavedPostController::class, 'getSavedPosts']);
Route::delete('/post/saved/delete/{userId}/{postId}', [SavedPostController::class, 'deleteSavedPost']);

Route::get('/post/category/all', [PostController::class, 'getPostCategories']);
Route::get('/post/category/{id}', [PostController::class, 'getPostSubCategories']);

Route::get('/message/chats/{userId}', [ChatController::class, 'getChats']);
Route::get('/message/messages/{chatId}', [ChatController::class, 'getMessages']);
Route::post('/message/create', [ChatController::class, 'createChat']);
Route::post('/message/send', [ChatController::class, 'sendMessage']);
Route::post('/message/image/upload', [ChatController::class, 'uploadImage']);
Route::put('/message/read/{userId}/{chatId}', [ChatController::class, 'readMessage']);


