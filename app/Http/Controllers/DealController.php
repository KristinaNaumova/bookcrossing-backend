<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Deal;
use App\Models\Response;
use App\Models\User;
use App\Services\NotificationService;
use DateTime;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            NotificationService::create($ad['user_id'], 'Вам пришло новое предложение по книге: '
                . $ad['book_name'] . ', ' . $ad['book_author']);

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

            NotificationService::create($response['user_id'], 'Ваш отклик на сделку отклонили. Книга: '
                . $ad['book_name'] . ', ' . $ad['book_author']);

            $response->delete();
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined response with id: ' . $responseId);
        }
    }

    function getMyResponses(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $user = User::find($userId);

        return $user->responses()->with('ad')->with('ad.responses')->get();
    }

    function getRequests(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $user = User::find($userId);

        $adsId = $user->ads()->pluck('id')->toArray();

        return Response::whereIn('ad_id', $adsId)->with('ad')->with('ad.responses')->get();
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

                DB::table('favourite_ads')->where('ad_id', $ad['id'])->delete();

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
                    'book_name' => $ad['book_name'],
                    'book_author' => $ad['book_author'],
                    'deadline' => $ad['deadline'],
                    'type' => $ad['type'],
                ]);

                NotificationService::create($response['user_id'], 'Сделка одобрена для книги: '
                    . $ad['book_name'] . ', ' . $ad['book_author']);
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

        Log::info($validatedData['status']);

        if ($validatedData['status'] == 'InProcess') {
            $deals->whereIn('deal_status', ['DealWaiting', 'RefundWaiting']);
        } else {
            $deals->where('deal_status', 'Finished');
        }

        $deals->where('first_member_id', $userId)->orderBy('deal_waiting_start_time', 'desc')
            ->orWhere('second_member_id', $userId);

        $dealsArray = $deals->get();
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
                'ad' => [
                    'book_name' => $deal['book_name'],
                    'book_author' => $deal['book_author'],
                    'type' => $deal['type'],
                    'deadline' => $deal['deadline'],
                ],
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
                'ad' => [
                    'book_name' => $deal['book_name'],
                    'book_author' => $deal['book_author'],
                    'type' => $deal['type'],
                    'deadline' => $deal['deadline'] ?? null,
                ],
            ];

            if ($deal['type'] = 'Exchange') {
                $result = array_merge($result, ['proposed_book' => $deal['proposed_book']]);
            }

            if ($deal['deal_status'] == 'DealWaiting') {
                if ($userId == $deal['second_member_id']) {
                    $result = array_merge($result, ['code' => $deal['code']]);

                    $userCurrentRole = 'Giver';
                } else {
                    $userCurrentRole = 'Getter';
                }
            } else {
                if ($userId == $deal['first_member_id']) {
                    $result = array_merge($result, ['code' => $deal['code']]);

                    $userCurrentRole = 'Giver';
                } else {
                    $userCurrentRole = 'Getter';
                }
            }

            $result = array_merge($result, ['user_current_role' => $userCurrentRole]);

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
                    'refund_waiting_end_time' => $ad['deadline'],
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

    function extendDealPeriod(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            $validatedData = $request->validate([
               'added_days' => 'required|min:1|integer'
            ]);

            if ($userId != $deal['first_member_id']) {
                abort(403, 'You dont have permission to extend deal period');
            }

            if ($deal['deal_status'] != 'RefundWaiting') {
                abort(400, 'The deal status is not "RefundWaiting"');
            }

            $date = new DateTime($deal['refund_waiting_end_time']);

            $date->modify('+' . $validatedData['added_days'] . ' days');
            $newDeadlineDate = $date->format('Y-m-d H:i:s');

            $deal->update([
                'refund_waiting_end_time' => $newDeadlineDate,
            ]);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }

    private function updateUserRating($userId, $evaluation)
    {
        $user = User::find($userId);
        $newAppraisersNumber = $user['appraisers_number'] + 1;

        $newRating = ((($user['rating'] ?? 0) * $user['appraisers_number']) + $evaluation) / $newAppraisersNumber;

        $newRating = number_format((float)$newRating, 2, '.', '');

        $user->update([
            'rating' => $newRating,
            'appraisers_number' => $newAppraisersNumber,
        ]);
    }

    function evaluateDeal(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            $validatedData = $request->validate([
                'evaluation' => 'required|min:1|max:10|integer'
            ]);

            if ($userId != $deal['first_member_id'] && $userId != $deal['second_member_id']) {
                abort(403, 'You dont have permission to evaluate this deal');
            }

            if ($deal['deal_status'] != 'Finished') {
                abort(403, 'You cannot rate this deal. It has not status "Finished"');
            }

            if ($userId == $deal['first_member_id']) {
                if ($deal['first_member_evaluation'] != null) {
                    abort(409, 'You already evaluate this deal');
                }

                DB::transaction(function () use ($deal, $validatedData) {
                    $deal->update([
                        'first_member_evaluation' => $validatedData['evaluation'],
                    ]);

                    $userWithUpdatedRatingId = $deal['second_member_id'];

                    $this->updateUserRating($userWithUpdatedRatingId, $validatedData['evaluation']);
                });
            }

            if ($userId == $deal['second_member_id']) {
                if ($deal['second_member_evaluation'] != null) {
                    abort(409, 'You already evaluate this deal');
                }

                DB::transaction(function () use ($deal, $validatedData) {
                    $deal->update([
                        'second_member_evaluation' => $validatedData['evaluation'],
                    ]);

                    $userWithUpdatedRatingId = $deal['first_member_id'];

                    $this->updateUserRating($userWithUpdatedRatingId, $validatedData['evaluation']);
                });
            }

        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }

    function cancelDeal(Request $request, $dealId)
    {
        try {
            $userId = $request['userInfo']['id'];

            $deal = Deal::findOrFail($dealId);

            if ($userId != $deal['first_member_id'] && $userId != $deal['second_member_id']) {
                abort(403, 'You dont have permission to cancel this deal');
            }

            if ($deal['deal_status'] != 'DealWaiting') {
                abort(409, 'You cannot cancel deal with this status');
            }

            DB::transaction(function () use ($deal, $userId) {
                $ad = Ad::find($deal['ad_id']);

                $ad->update([
                    'status' => 'Archived',
                ]);

                $deal->delete();

                $anotherUserId = $this->getAnotherUserId($userId, $deal['first_member_id'], $deal['second_member_id']);
                NotificationService::create($anotherUserId, 'Сделка была отменена другим пользователем для книги: '
                    . $ad['book_name'] . ', ' . $ad['book_author']);
            });
        } catch (ModelNotFoundException $e) {
            abort(404, 'Undefined deal with id: ' . $dealId);
        }
    }
}
