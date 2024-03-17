<?php

namespace App\Modules\Ad\DTO;

class AdCreateDTO
{
    public function __construct(
        public array $userInfo,
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
