<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Response;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    function createAd(Request $request)
    {
        $userId = $request['userInfo']['id'];


        $validatedData = $request->validate([
            'book_name' => 'required|string',
            'book_author' => 'required|string',
            'description' => 'nullable|string',
            'comment' => 'nullable|string',
            'type' => 'in:Gift,Exchange,Rent',
            'genres' => 'required|array',
            'genres.*' => Rule::exists('genres', 'id'),
            'deadline' => 'nullable|string',
            'timezone' => 'required|string'
        ]);

        $deadline = Carbon::parse($validatedData['deadline'])
            ->setTimezone($validatedData['timezone']) // Устанавливаем временную зону пользователя
            ->toDateTimeString();

        if (!key_exists('deadline', $validatedData) && $validatedData['type'] == 'Rent') {
            abort(409, 'You need to set days amount deadline with ad type "Rent"');
        }

        DB::transaction(function () use ($userId, $validatedData, $deadline) {
            $ad = Ad::create([
                'user_id' => $userId,
                'book_name' => $validatedData['book_name'],
                'book_author' => $validatedData['book_author'],
                'description' => $validatedData['description'],
                'comment' => $validatedData['comment'],
                'type' => $validatedData['type'],
                'deadline' => $deadline ?? null,
                'published_at' => date('Y-m-d H:i'),
            ]);

            foreach ($validatedData['genres'] as $genre) {
                $ad->genres()->attach($genre);;
            }

            return response()->json($ad);
        });

    }

    function getMyAds(Request $request)
    {
        $userId = $request['userInfo']['id'];

        return Ad::where('user_id', $userId)->where('status', 'Active')->orWhere('status', 'inDeal')->with('genres')->get();
    }

    function getMyArchiveAds(Request $request)
    {
        $userId = $request['userInfo']['id'];

        return Ad::where('user_id', $userId)->where('status', 'Archived')->get();
    }

    function moveAdToArchive(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            if ($ad['user_id'] != $userId) {
                abort(403, 'This ad is not available to this user');
            }

            if ($ad['status'] == 'InDeal') {
                abort(403, 'You cannot move ad in deal to archive');
            }

            if ($ad['status'] == 'Archived') {
                abort(409, 'This ad is already in archive');
            }

            DB::transaction(function () use ($ad) {
                $ad->update([
                    'status' => 'Archived',
                    'published_at' => null,
                ]);

                DB::table('favourite_ads')->where('ad_id', $ad['id'])->delete();

                Response::where('ad_id', $ad['id'])->delete();
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function publishAdFromArchive(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            if ($ad['user_id'] != $userId) {
                abort(403, 'This ad is not available to this user');
            }

            if ($ad['status'] !== 'Archived') {
                abort(409, 'This ad is not in archive');
            }

            $ad->update([
                'status' => 'Active',
                'published_at' => date('Y-m-d H:i'),
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function updateAd(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            if ($ad['user_id'] != $userId) {
                abort(403, 'This ad is not available to this user');
            }

            if ($ad['status'] == 'InDeal') {
                abort(403, 'You cannot move ad in deal to archive');
            }

            $validatedData = $request->validate([
                'book_name' => 'required|string',
                'book_author' => 'required|string',
                'description' => 'nullable|string',
                'comment' => 'nullable|string',
                'type' => 'in:Gift,Exchange,Rent',
                'genres' => 'required|array',
                'genres.*' => Rule::exists('genres', 'id'),
                'deadline' => 'nullable|string',
                'timezone' => 'required|string',
            ]);

            if (!key_exists('deadline', $validatedData) && $validatedData['type'] == 'Rent') {
                abort(409, 'You need to set days amount deadline with ad type "Rent"');
            }

            $deadline = Carbon::parse($validatedData['deadline'])
                ->setTimezone($validatedData['timezone']) // Устанавливаем временную зону пользователя
                ->toDateTimeString(); // Форматируем как строку даты и времени

            DB::transaction(function () use ($ad, $validatedData, $deadline) {
                $ad->update([
                    'book_name' => $validatedData['book_name'],
                    'book_author' => $validatedData['book_author'],
                    'description' => $validatedData['description'],
                    'comment' => $validatedData['comment'],
                    'type' => $validatedData['type'],
                    'deadline' => $deadline ?? null,
                ]);

                $ad->genres()->detach();
                foreach ($validatedData['genres'] as $genre) {
                    $ad->genres()->attach($genre);;
                };;
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function getAllAds(Request $request)
    {
        $validatedData = $request->validate([
            'sort' => 'nullable|in:AlphabetDesc,AlphabetAsc,DateDesc,DateAsc,Rating,Preferences',
            'genres' => 'nullable|string', // Оставляем как string для валидации
            'type' => 'nullable|string', // Оставляем как string для валидации
            'page' => 'nullable|integer|min:0',
            'word' => 'nullable|string',
            'locations' => 'nullable|string', // Новое поле для фильтрации по местоположениям
        ]);

        if (isset($validatedData['genres']) && is_string($validatedData['genres'])) {
            $validatedData['genres'] = explode(',', $validatedData['genres']);
        }

        if (isset($validatedData['locations']) && is_string($validatedData['locations'])) {
            $validatedData['locations'] = explode(',', $validatedData['locations']);
        }

        // Преобразуем type в массив, если он передан как строка
        if (isset($validatedData['type']) && is_string($validatedData['type'])) {
            $validatedData['type'] = explode(',', $validatedData['type']);
        }

        $userId = $request['userInfo']['id'];

        $ads = Ad::query()->where('status', 'Active');

        // Фильтрация по типу (мультивыбор)
        if (!empty($validatedData['type'])) {
            $ads->whereIn('type', $validatedData['type']);
        }


        if (key_exists('word', $validatedData)) {
            $ads->where(function ($query) use ($validatedData) {
                $query->where('book_name', 'LIKE', '%' . $validatedData['word'] . '%')
                    ->orWhere('book_author', 'LIKE', '%' . $validatedData['word'] . '%');
            });
        }

        if (key_exists('locations', $validatedData) && !empty($validatedData['locations'])) {
            $ads->whereHas('user.locations', function ($query) use ($validatedData) {
                $query->whereIn('location_id', $validatedData['locations']);
            });
        }

        // Фильтрация по жанрам (мультивыбор)
        if (key_exists('genres', $validatedData) && !empty($validatedData['genres'])) {
            $ads->whereHas('genres', function ($query) use ($validatedData) {
                $query->whereIn('genre_id', $validatedData['genres']);
            });
        }

        if (key_exists('sort', $validatedData)) {
            switch ($validatedData['sort']) {
                case 'AlphabetDesc':
                    $ads->orderBy('book_name', 'DESC');
                    break;
                case 'AlphabetAsc':
                    $ads->orderBy('book_name', 'ASC');
                    break;
                case 'DateDesc':
                    $ads->orderBy('published_at', 'DESC');
                    break;
                case 'DateAsc':
                    $ads->orderBy('published_at', 'ASC');
                    break;
                case 'Rating':
                    $ads->join('users', 'ads.user_id', '=', 'users.id')
                        ->orderByDesc('users.rating')
                        ->select('ads.*');
                    break;
                case 'Preferences':
                    $ads->leftJoin('ad_genre', 'ads.id', '=', 'ad_genre.ad_id')
                        ->leftJoin('genre_user', function ($join) use ($userId) {
                            $join->on('ad_genre.genre_id', '=', 'genre_user.genre_id')
                                ->where('genre_user.user_id', '=', $userId);
                        })
                        ->select('ads.*', DB::raw('IF(genre_user.genre_id IS NULL, 0, 1) AS preferred'))
                        ->orderByDesc('preferred');
                    break;
            }
        } else {
            $ads->orderBy('published_at', 'DESC');
        }

        $adsOnPage = 15;
        $adsCount = $ads->count();
        $pagesCount = ceil($adsCount / 15);

        if ($pagesCount == 0) {
            $pagesCount = 1;
        }

        $currentPage = $validatedData['page'] ?? 1;

        if ($currentPage > $pagesCount) {
            abort(409, 'Incorrect page');
        }

        $offset = ($adsOnPage * ($currentPage - 1));
        $ads->offset($offset)->limit($adsOnPage);

        $pagination = [
            'size' => $adsOnPage,
            'pagesCount' => $pagesCount,
            'currentPageNumber' => $currentPage,
        ];

        return [
            'ads' => $ads->with('genres')->with('responses')->with('user')->with('user.locations')->get(),
            'pagination' => $pagination,
        ];
    }

    function searchAds(Request $request, $word)
    {
        $validatedData = $request->validate([
            'page' => 'nullable|integer|min:0'
        ]);

        $ads = Ad::where(function ($query) use ($word) {
            $query->where('book_name', 'LIKE', '%' . $word . '%')
                ->orWhere('book_author', 'LIKE', '%' . $word . '%');
        });

        $adsOnPage = 15;

        $adsCount = $ads->count();

        $pagesCount = ceil($adsCount / 15);

        if ($pagesCount == 0) {
            $pagesCount = 1;
        }

        if (key_exists('page', $validatedData)) {
            $currentPage = $validatedData['page'];
        } else {
            $currentPage = 1;
        }

        if ($currentPage > $pagesCount) {
            abort(409, 'Incorrect page');
        }

        $offset = ($adsOnPage * ($currentPage - 1));

        $ads->offset($offset)->limit($adsOnPage);

        $pagination = [
            'size' => $adsOnPage,
            'pagesCount' => $pagesCount,
            'currentPageNumber' => $currentPage,
        ];
        return [
            'ads' => $ads->with('genres')->with('user')->get(),
            'pagination' => $pagination,
        ];
    }

    function getConcreteAd(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            $isUsersAd = true;

            if ($ad['user_id'] != $userId) {
                $isUsersAd = false;

                if ($ad['status'] != 'Active') {
                    abort(403, 'You dont have permission to get this ad');
                }
            }

            return array_merge(Ad::where('id', $adId)
                ->with('genres')
                ->with('user')
                ->with('responses')
                ->first()
                ->toArray(),
                ['is_users_ad' => $isUsersAd],
            ['locations' => User::find($ad['user_id'])->locations()->get()],
            );
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }


    function getAllFavouriteAds(Request $request)
    {
        $validatedData = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:ads,id',
        ]);

        $ads = Ad::whereIn('id', $validatedData['ids'])
            ->with('genres')
            ->with('user')
            ->with('responses')
            ->get();

        return response()->json($ads);
    }


    function getFavoritesCard(Request $request, $adId)
    {
        $userId = $request['userInfo']['id'];

        return response()->json(DB::table('favourite_ads')
            ->where('user_id', $userId)
            ->where('ad_id', $adId)
            ->first() ?? null);
    }

    function addAdToFavourite(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            if ($ad['status'] != 'Active') {
                abort(409, 'You cannot add ad with this status');
            }

            if (DB::table('favourite_ads')
                ->where('user_id', $userId)
                ->where('ad_id', $adId)
                ->exists()) {
                abort(409, 'This ad is already in favourites');
            }

            DB::table('favourite_ads')->insert([
                'ad_id' => $ad['id'],
                'user_id' => $userId,
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function removeFromFavourite(Request $request, $adId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $ad = Ad::findOrFail($adId);

            if (!DB::table('favourite_ads')
                ->where('user_id', $userId)
                ->where('ad_id', $adId)
                ->exists()) {
                abort(409, 'This ad is not in favourites');
            }

            DB::table('favourite_ads')
                ->where('user_id', $userId)
                ->where('ad_id', $adId)
                ->delete();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }
}
