<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DealControllerService
{
    public function checkWaitingDeals()
    {
        $deals = Deal::where('deal_status', 'DealWaiting')
            ->where('deal_waiting_end_time', '<=', date('Y-m-d H:i'))
            ->get();

        foreach ($deals as $deal) {
            $dealId = $deal['id'];

            $dealFromDB = Deal::find($dealId);

            DB::transaction(function () use ($dealFromDB, $dealId) {
                $ad = Ad::find($dealId);

                $ad->update([
                    'status' => 'Active',
                ]);

                NotificationService::create($dealFromDB['first_member_id'], 'Сделка была автоматически отклонена - книга не была передана. Книга: '
                    . $ad['book_name'] . ', ' . $ad['book_author']);

                NotificationService::create($dealFromDB['second_member_id'], 'Сделка была автоматически отклонена - книга не была передана. Книга: '
                    . $ad['book_name'] . ', ' . $ad['book_author']);
                $dealFromDB->delete();
            });
        }
    }

    public function checkDealsInProcess()
    {
        $deals = Deal::where('deal_status', 'RefundWaiting')
            ->where('refund_waiting_end_time', '<=', date('Y-m-d H:i'))
            ->get();

        foreach ($deals as $deal) {
            $userId = $deal['second_member_id'];

            $user = User::find($userId);
            $userNewRating = number_format((float)(($user['rating'] ?? 0) - 0.01), 2, '.', '');

            $user->update([
                'rating' => $userNewRating,
            ]);
        }
    }
}
