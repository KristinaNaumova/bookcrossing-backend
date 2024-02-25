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
                $proposedBook = Response::where('ad_id', $ad['id'])->first()['proposed_book'] ?? null;

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
                    'proposed_book' => $proposedBook,
                    'code' => random_int(100000, 999999),
                ]);
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined response with id: ' . $responseId);
        }
    }

    private function getAnotherUserId($userId, $firstUserId, $secondUserId)
    {
        if ($userId == $firstUserId) {
            return $secondUserId;
        } else {
            return $firstUserId;
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
            ->orWhere('second_member_id', $userId);

        $dealsArray = $deals->with('ad')->get();
        $result = [];

        foreach ($dealsArray as $deal) {
            $anotherUserId = $this->getAnotherUserId($userId, $deal['first_member_id'], $deal['second_member_id']);
            $anotherUserName = User::find($anotherUserId)['name'];

            if ($userId == $deal['first_member_id']) {
                $userEvaluation = $deal['first_member_evaluation'] ?? null;
            } else {
                $userEvaluation = $deal['second_member_evaluation'] ?? null;
            }

            $result[] = [
                'id' => $deal['id'],
                'deal_status' => $deal['deal_status'],
                'deal_waiting_start_time' => $deal['deal_waiting_start_time'],
                'ad' => $deal['ad'],
                'another_user_id' => $anotherUserId,
                'another_user_name' => $anotherUserName,
                'user_evaluation' => $userEvaluation,
            ];
        }

        return $result;
    }

    function getConcreteDeal(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            if ($deal['deal_status'] == 'Finished') {
                abort(409, 'This deal is finished. Yoy cannot get full information');
            }

            if ($deal['first_member_id'] != $userId && $deal['second_member_id'] != $userId) {
                abort(403, 'You dont have permission to get this deal');
            }

            $isUserGiver = false;

            if ($userId == $deal['first_member_id']) {
                $isUserGiver = true;
            }

            $ad = Ad::find($deal['ad_id']);

            if ($ad['type'] == 'Exchange') {
                $isUserGiver = true;
            }

            $anotherUserId = $this->getAnotherUserId($userId, $deal['first_member_id'], $deal['second_member_id']);
            $anotherUser = User::find($anotherUserId);

            $result = [
                'id' => $deal['id'],
                'deal_status' => $deal['deal_status'],
                'first_member_id' => $deal['first_member_id'],
                'second_member_id' => $deal['second_member_id'],
                'deal_waiting_start_time' => $deal['deal_waiting_start_time'],
                'deal_waiting_end_time' => $deal['deal_waiting_end_time'],
                'refund_waiting_start_time' => $deal['refund_waiting_start_time'],
                'refund_waiting_end_time' => $deal['refund_waiting_end_time'],
                'another_user_id' => $anotherUserId,
                'another_user_name' => $anotherUser['name'],
                'another_user_contacts' => $anotherUser->contacts()->get(),
                'ad' => $ad,
            ];

            if ($ad['type'] = 'Exchange') {
                $result = array_merge($result, ['proposed_book' => $deal['proposed_book']]);
            }

            if ($deal['deal_status'] == 'DealWaiting') {
                if ($userId == $deal['second_member_id']) {
                    $result = array_merge($result, ['code' => $deal['code']]);
                }
            } else {
                if ($userId == $deal['first_member_id']) {
                    $result = array_merge($result, ['code' => $deal['code']]);
                }
            }

            return $result;
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }

    function confirmBookTransfer(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            $validatedData = $request->validate([
                'code' => 'required|integer|min:6'
            ]);

            if ($deal['deal_status'] != 'DealWaiting') {
                abort(400, 'The deal status is not "DealWaiting"');
            }

            if ($userId != $deal['first_member_id']) {
                abort(403, 'You dont have permission to confirm transfer');
            }

            if ($deal['code'] != $validatedData['code']) {
                abort(400, 'Wrong code');
            }

            DB::transaction(function () use ($deal) {
                $ad = Ad::find($deal['ad_id']);

                if ($ad['type'] != 'Rent') {
                    $deal->update([
                        'deal_status' => 'Finished',
                    ]);

                    $ad->update([
                        'status' => 'Archived',
                    ]);

                    return;
                }

                $deal->update([
                    'deal_status' => 'RefundWaiting',
                    'refund_waiting_start_time' => date('Y-m-d H:i'),
                    'refund_waiting_end_time' => date('Y-m-d H:i', strtotime($ad['deadline'] . ' days')),
                    'code' => random_int(100000, 999999),
                ]);
            });

        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }

    function confirmDealEnding(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            $validatedData = $request->validate([
                'code' => 'required|integer|min:6'
            ]);

            if ($deal['deal_status'] != 'RefundWaiting') {
                abort(400, 'The deal status is not "RefundWaiting"');
            }

            if ($userId != $deal['second_member_id']) {
                abort(403, 'You dont have permission to confirm deal ending');
            }

            if ($deal['code'] != $validatedData['code']) {
                abort(400, 'Wrong code');
            }

            DB::transaction(function () use ($deal) {
                $ad = Ad::find($deal['ad_id']);

                $deal->update([
                    'deal_status' => 'Finished',
                ]);

                $ad->update([
                    'status' => 'Archived',
                ]);
            });

        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }
}
