<?php

namespace App\Http\Controllers;

use App\Models\Ad;
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

            $ad->update([
                'status' => 'Archived'
            ]);
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
}
