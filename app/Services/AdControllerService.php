<?php

namespace App\Services;

use App\Models\Ad;

class AdControllerService
{
    public function checkAdDeadline()
    {
        $expiredAds = Ad::where('status', 'Active')->where('type', 'Rent')
            ->where('deadline', '<=', date('Y-m-d H:i'));

        $expiredAds->update([
            'status' => 'Archived',
        ]);

        foreach ($expiredAds->get()->toArray() as $ad) {
            NotificationService::create($ad['user_id'], 'Объявление было перемещено в архив, так как конечный срок сделки был просрочен');
        }
    }
}
