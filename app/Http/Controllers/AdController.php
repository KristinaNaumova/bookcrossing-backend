<?php

namespace App\Http\Controllers;

use App\Models\Ad;
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
}
