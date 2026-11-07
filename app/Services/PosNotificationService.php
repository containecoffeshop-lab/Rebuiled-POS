<?php

namespace App\Services;

use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\PosActivity;
use Illuminate\Support\Facades\Notification;

class PosNotificationService
{
    public const CATEGORIES = [
        'orders' => 'Pesanan online dan pembatalan',
        'sales' => 'Transaksi dan pembayaran',
        'kitchen' => 'Pesanan siap disajikan',
        'shifts' => 'Buka dan tutup shift',
    ];

    public const ROLES = ['manager' => 'Pengelola', 'cashier' => 'Kasir'];

    public function notifyStaff(string $title, string $message, string $url, string $category): void
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            throw new \InvalidArgumentException('Jenis notifikasi tidak dikenal.');
        }

        $settings = StoreSetting::query()->whereIn('key', array_map(
            fn (string $role): string => 'notification_categories_'.$role,
            array_keys(self::ROLES),
        ))->pluck('value', 'key');

        $recipients = User::query()->where('is_active', true)->get()->filter(function (User $user) use ($settings, $category): bool {
            $setting = $settings->get('notification_categories_'.$user->role);
            if ($setting === null) {
                return true;
            }

            $categories = json_decode($setting, true, flags: JSON_THROW_ON_ERROR);

            return is_array($categories) && in_array($category, $categories, true);
        });

        Notification::send(
            $recipients,
            new PosActivity($title, $message, $url, $category),
        );
    }
}
