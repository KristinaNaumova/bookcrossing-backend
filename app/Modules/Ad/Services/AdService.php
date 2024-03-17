<?php

namespace App\Modules\Ad\Services;

use App\Models\Response;
use App\Modules\Ad\DTO\AdCreateDTO;
use App\Modules\Ad\Models\Ad;
use Illuminate\Support\Facades\DB;

class AdService
{
    public function createAd(AdCreateDTO $dto)
    {

        $userId = $dto->userInfo['id'];

        if (!$dto->deadline && $dto->type == 'Rent') {
            abort(409, 'You need to set days amount deadline with ad type "Rent"');
        }

        DB::transaction(function () use ($userId, $dto) {
            $ad = Ad::create([
                'user_id' => $userId,
                'book_name' => $dto->bookName,
                'book_author' => $dto->bookAuthor,
                'description' => $dto->description,
                'comment' => $dto->comment,
                'type' => $dto->type,
                'deadline' => $dto->deadline,
                'published_at' => date('Y-m-d H:i'),
            ]);

            $ad->genres()->attach($dto->genres);
        });
    }

    public function moveAdToArchive($userId, $adId)
    {
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
    }
}
