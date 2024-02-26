<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function createNotification($userId, $text)
    {
        Notification::create([
            'user_id' => $userId,
            'date' => date('Y-m-d H:i'),
            'is_new' => 1,
            'text' => $text,
        ]);
    }
}
