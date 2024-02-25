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

Route::prefix('/genre')->controller('App\Http\Controllers\GenreController')->group(function () {
    Route::middleware('auth.jwt')->group(function () {
        Route::get('', 'getAllGenres');
    });
});

Route::prefix('/ad')->controller('App\Http\Controllers\AdController')->group(function () {
    Route::middleware('auth.jwt')->group(function () {
        Route::post('', 'createAd');
        Route::get('/my', 'getMyAds');
        Route::get('/my/archived', 'getMyArchiveAds');
        Route::post('/{adId}/archive', 'moveAdToArchive');
        Route::post('/{adId}/publish', 'publishAdFromArchive');
        Route::put('/{adId}', 'updateAd');
        Route::get('', 'getAllAds');
        Route::get('/search/{word}', 'searchAds');
        Route::get('/favourite', 'getAllFavouriteAds');
        Route::post('/{adId}/favourite', 'addAdToFavourite');
        Route::delete('/{adId}/favourite', 'removeFromFavourite');
        Route::get('/{adId}', 'getConcreteAd');
    });
});

Route::prefix('/deal')->controller('App\Http\Controllers\DealController')->group(function () {
    Route::middleware('auth.jwt')->group(function () {
        Route::post('/offer/{adId}', 'offerDeal');
        Route::delete('/offer/{responseId}', 'cancelDealOffer');
        Route::post('/reject/{responseId}', 'rejectDealResponse');
        Route::get('/my/response', 'getMyResponses'); // отклики которые ОТПРАВИЛ пользователь
        Route::get('/request', 'getRequests'); // запросы которые ОТПРАВИЛИ пользователю
        Route::post('/offer/{responseId}/accept', 'acceptDealResponse');
        Route::get('', 'getAllMyDeals');
        Route::get('/{dealId}', 'getConcreteDeal');
        Route::post('/{dealId}/confirm_book_transfer', 'confirmBookTransfer');
    });
});

Route::fallback(function () {
    return response(['message' => 'Undefined route'], 404);
});
