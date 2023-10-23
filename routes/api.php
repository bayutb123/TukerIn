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
Route::get('/post/all', [PostController::class, 'getPosts']);
Route::get('/post/get/{id}', [PostController::class, 'getPost']);
Route::delete('/post/delete/{id}', [PostController::class, 'deletePost']);
