<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('/auth')->controller('App\Http\Controllers\AuthController')->group(function () {
    Route::post('/login', 'login');
    Route::post('/refresh', 'refresh');
});

Route::prefix('/user')->controller('App\Http\Controllers\UserController')->group(function () {
    Route::middleware('auth.jwt')->group(function () {
        Route::get('/my', 'getMyInfo');
        Route::get('/{id}', 'getConcreteUserInfo');
        Route::put('/my/location', 'updateUserLocations');
        Route::put('/my/contact', 'updateUserContacts');
        Route::get('/my/genre', 'getUserFavouriteGenres');
        Route::put('/my/genre', 'updateUserFavouriteGenres');
    });
});

Route::prefix('/location')->controller('App\Http\Controllers\LocationController')->group(function () {
    Route::middleware('auth.jwt')->group(function () {
       Route::get('', 'getAllLocations');
    });
});

Route::fallback(function () {
    return response(['message' => 'Undefined route'], 404);
});
