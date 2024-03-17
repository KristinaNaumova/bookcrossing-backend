<?php

namespace App\Modules\Ad\Services;

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

            $ad->genres()->attach($dto->genres);;
        });
    }
}
