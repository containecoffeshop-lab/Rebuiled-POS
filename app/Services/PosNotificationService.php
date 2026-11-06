<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PosActivity;
use Illuminate\Support\Facades\Notification;

class PosNotificationService
{
    public function notifyStaff(string $title, string $message, string $url): void
    {
        Notification::send(
            User::query()->where('is_active', true)->get(),
            new PosActivity($title, $message, $url),
        );
    }
}
