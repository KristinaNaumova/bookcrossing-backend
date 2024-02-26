<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function create($userId, $text)
    {
        Notification::create([
            'user_id' => $userId,
            'date' => date('Y-m-d H:i'),
            'is_new' => 1,
            'message' => $text,
        ]);
    }
}
