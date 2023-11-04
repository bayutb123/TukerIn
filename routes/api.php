<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\LoginController;


Route::post('/user/register', [RegisterController::class, 'create']);
Route::post('/user/login', [LoginController::class, 'login']);
Route::post('/user/update', [UserController::class, 'update']);

Route::post('/post/create', [PostController::class, 'create']);
Route::post('/post/uploadImage', [PostController::class, 'uploadImage']);
Route::post('/post/save', [PostController::class, 'savePost']);
Route::get('/post/all/{userId}', [PostController::class, 'getPosts']);
Route::get('/post/get/{id}', [PostController::class, 'getPost']);
Route::get('/post/saved/{id}', [PostController::class, 'getSavedPosts']);
Route::get('/post/search/{query}/{id}', [PostController::class, 'searchPost']);
Route::get('/post/search/suggestion/{query}/{id}', [PostController::class, 'searchSuggestion']);
Route::put('/post/update', [PostController::class, 'updatePost']);
Route::delete('/post/delete/{id}', [PostController::class, 'deletePost']);
Route::delete('/post/saved/delete/{userId}/{postId}', [PostController::class, 'deleteSavedPost']);
