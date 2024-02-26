<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    function getAllNotifications(Request $request)
    {
        $userId = $request['userInfo']['id'];

        $notifications = DB::transaction(function () use ($userId) {
            $notifications = Notification::where('user_id', $userId)
                ->orderBy('date', 'DESC')
                ->limit(50)
                ->get();

            Notification::where('user_id', $userId)
                ->where('is_new', 1)
                ->orderBy('date', 'DESC')
                ->limit(50)
                ->update([
                    'is_new' => false
                ]);

            return $notifications;
        });

        return $notifications;
    }

    function getNewNotificationsCount(Request $request)
    {
        $userId = $request['userInfo']['id'];

        return Notification::where('user_id', $userId)
            ->where('is_new', 1)
            ->count();
    }
}
