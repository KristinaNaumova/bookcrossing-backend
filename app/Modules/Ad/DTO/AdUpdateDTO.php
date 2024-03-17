<?php

namespace App\Modules\Ad\DTO;

class AdUpdateDTO
{
    public function __construct(
        public array $userInfo,
        public int $adId,
        public string $bookName,
        public string $bookAuthor,
        public ?string $description,
        public ?string $comment,
        public string $type,
        public array $genres,
        public ?int $deadline,
    )
    {
    }
}

