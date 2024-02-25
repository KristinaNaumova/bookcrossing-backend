<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Deal;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            if ($ad['type'] == 'Exchange') {
                $proposedBook = $validated_data['proposed_book'];
            } else {
                $proposedBook = null;
            }

            Response::insert([
                'user_id' => $userId,
                'ad_id' => $ad['id'],
                'proposed_book' => $proposedBook,
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined ad with id: ' . $adId);
        }
    }

    function cancelDealOffer(Request $request, $responseId)
    {
        try {
            $response = Response::findOrFail($responseId);

            $userId = $request['userInfo']['id'];

            if ($response['user_id'] != $userId) {
                abort(403, 'You dont have permission to cancel this response');
            }

            $response->delete();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined response with id: ' . $responseId);
        }
    }

    function rejectDealResponse(Request $request, $responseId)
    {
        try {
            $response = Response::findOrFail($responseId);

            $userId = $request['userInfo']['id'];

            $ad = Ad::find($response['ad_id']);

            if ($ad['user_id'] != $userId) {
                abort(403, 'You dont have permission to reject this response');
            }

            $response->delete();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined response with id: ' . $responseId);
        }
    }

    function getMyResponses(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $user = User::find($userId);

        return $user->responses()->with('ad')->get();
    }

    function getRequests(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $user = User::find($userId);

        $adsId = $user->ads()->pluck('id')->toArray();

        return Response::whereIn('ad_id', $adsId)->with('ad')->get();
    }

    function acceptDealResponse(Request $request, $responseId)
    {
        try {
            $response = Response::findOrFail($responseId);

            $userId = $request['userInfo']['id'];

            $ad = Ad::find($response['ad_id']);

            if ($ad['user_id'] != $userId) {
                abort(403, 'You dont have permission to accept this response');
            }

            DB::transaction(function () use ($ad, $userId, $response) {
                Response::where('ad_id', $ad['id'])->delete();

                $ad->update([
                    'status' => 'InDeal',
                ]);

                Deal::insert([
                    'first_member_id' => $userId,
                    'second_member_id' => $response['user_id'],
                    'ad_id' => $ad['id'],
                    'deal_status' => 'DealWaiting',
                    'deal_waiting_start_time' => date('Y-m-d H:i'),
                    'deal_waiting_end_time' => date('Y-m-d H:i', strtotime('7 days')),
                ]);
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined response with id: ' . $responseId);
        }
    }

    function getAllMyDeals(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $validatedData = $request->validate([
            'status' => 'required|in:InProcess,Finished',
        ]);

        $deals = Deal::query();

        if ($validatedData['status'] == 'InProcess') {
            $deals->whereIn('deal_status', ['DealWaiting', 'RefundWaiting']);
        } else {
            $deals->where('deal_status', 'Finished');
        }

        $deals->where('first_member_id', $userId)
            ->orWhere('second_member_id', $userId)
            ->select('id', 'deal_status', 'deal_waiting_start_time', 'ad_id');

        return $deals->with('ad')->get();
    }
}
