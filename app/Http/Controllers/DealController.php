<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class DealController extends Controller
{
    function offerDeal(Request $request, $adId)
    {
        try {
            $validated_data = $request->validate([
                'proposed_book' => 'nullable|string',
            ]);

            $ad = Ad::findOrFail($adId);

            $userId = $request['userInfo']['id'];

            if (Response::where('user_id', $userId)
                ->where('ad_id', $ad['id'])
                ->exists()) {
                abort(409, 'You are already offer deal to this ad');
            }

            if ($ad['user_id'] == $userId) {
                abort(409, 'You cannot offer deal to your own ad');
            }

            if ($ad['status'] != 'Active') {
                abort(409, 'You cannot offer deal to ad with this status');
            }

            if ($ad['type'] == 'Exchange' && (!key_exists('proposed_book', $validated_data) || !$validated_data['proposed_book'])) {
                abort(400, 'You dont point proposed book');
            }

            Response::insert([
                'user_id' => $userId,
                'ad_id' => $ad['id'],
                'proposed_book' => $validated_data['proposed_book'] ?? null,
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function cancelDealOffer(Request $request, $adId)
    {
        try {
            $ad = Ad::findOrFail($adId);

            $userId = $request['userInfo']['id'];

            if (!Response::where('user_id', $userId)
                ->where('ad_id', $ad['id'])
                ->exists()) {
                abort(400, 'There is not this deal offer');
            }

            Response::where('user_id', $userId)
                ->where('ad_id', $ad['id'])
                ->delete();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }
}
