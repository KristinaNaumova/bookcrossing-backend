<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\User;
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
            'deadline' => 'nullable|integer',
        ]);

        if (!key_exists('deadline', $validatedData) && $validatedData['type'] == 'Rent') {
            abort(403, 'You need to set days amount deadline with ad type "Rent"');
        }

        DB::transaction(function () use ($userId, $validatedData) {
            $ad = Ad::create([
                'user_id' => $userId,
                'book_name' => $validatedData['book_name'],
                'book_author' => $validatedData['book_author'],
                'description' => $validatedData['description'],
                'comment' => $validatedData['comment'],
                'type' => $validatedData['type'],
                'deadline' => $validatedData['deadline'] ?? null,
                'published_at' => date('Y-m-d H:i'),
            ]);

            $ad->genres()->attach($validatedData['genres']);;
        });
    }

    function getMyAds(Request $request)
    {
        $userId = $request['userInfo']['id'];

        return Ad::where('user_id', $userId)->where('status', 'Active')->orWhere('status', 'inDeal')->get();
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
                'deadline' => 'nullable|integer',
            ]);

            if (!key_exists('deadline', $validatedData) && $validatedData['type'] == 'Rent') {
                abort(403, 'You need to set days amount deadline with ad type "Rent"');
            }

            DB::transaction(function () use ($ad, $validatedData) {
                $ad->update([
                    'book_name' => $validatedData['book_name'],
                    'book_author' => $validatedData['book_author'],
                    'description' => $validatedData['description'],
                    'comment' => $validatedData['comment'],
                    'type' => $validatedData['type'],
                    'deadline' => $validatedData['deadline'] ?? null,
                ]);

                $ad->genres()->detach();
                $ad->genres()->attach($validatedData['genres']);;
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function getAllAds(Request $request)
    {
        $validatedData = $request->validate([
            'sort' => 'nullable|in:AlphabetDesc,AlphabetAsc,DateDesc,DateAsc,Rating,Preferences',
            'genres' => 'nullable|array',
            'genres.*' => Rule::exists('genres', 'id'),
            'type' => 'nullable|in:Exchange,Gift,Rent',
            'page' => 'nullable|integer|min:0'
        ]);

        $userId = $request['userInfo']['id'];

        $ads = Ad::query()->where('status', 'Active');

        if (key_exists('type', $validatedData)) {
            $ads->where('type', $validatedData['type']);
        }

        if (key_exists('genres', $validatedData)) {
            $ads->leftJoin(DB::raw('ad_genre as ad_genre_table'), 'ads.id', '=', 'ad_genre_table.ad_id')
                ->whereIn('ad_genre_table.genre_id', $validatedData['genres'])
                ->select('ads.*')
                ->distinct();
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
                case 'Preferences':
                    $ads->leftJoin('ad_genre', 'ads.id', '=', 'ad_genre.ad_id')
                        ->leftJoin('genre_user', function ($join) use ($userId) {
                            $join->on('ad_genre.genre_id', '=', 'genre_user.genre_id')
                                ->where('genre_user.user_id', '=', $userId);
                        })
                        ->select('ads.*', DB::raw('IF(genre_user.genre_id IS NULL, 0, 1) AS preferred'))
                        ->orderByDesc('preferred');
            }
        }

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

            return array_merge(Ad::where('id', $adId)->with('genres')
                ->with('user')
                ->first()
                ->toArray(), ['isUsersAd' => $isUsersAd]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function getAllFavouriteAds(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $ads = Ad::whereIn('id', DB::table('favourite_ads')
            ->where('user_id', $userId)->pluck('ad_id')
            ->toArray())
            ->with('genres')
            ->with('user')
            ->get();

        return $ads;
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
}
