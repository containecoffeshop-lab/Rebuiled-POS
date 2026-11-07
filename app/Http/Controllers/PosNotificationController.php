<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\User;
use App\Services\ModuleAccess;
use App\Services\PosNotificationService;
use App\Services\RecipeInventory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class PosNotificationController extends Controller
{
    public function checkStock(RecipeInventory $inventory, PosNotificationService $notifications): JsonResponse
    {
        DB::transaction(function () use ($inventory, $notifications): void {
            $materials = Material::query()->with('unit')->orderBy('id')->lockForUpdate()->get();
            $stock = $inventory->onHandByMaterial($materials->pluck('id')->all());
            foreach ($materials as $material) {
                $quantity = (float) $stock->get($material->id, 0);
                $low = $quantity < (float) $material->minimum_quantity;
                if ($low && ! $material->low_stock_alerted) {
                    $notifications->notifyStaff(
                        'Stok bahan di bawah minimum',
                        $material->name.': '.number_format($quantity, 3, ',', '.').' '.$material->unit->name.' tersedia; minimum '.number_format((float) $material->minimum_quantity, 3, ',', '.').'.',
                        route('pos.index', ['module' => 'stok']),
                        'stock',
                    );
                }
                if ($low !== (bool) $material->low_stock_alerted) {
                    $material->forceFill(['low_stock_alerted' => $low])->save();
                }
            }
        }, attempts: 3);

        return response()->json(['checked' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->visibleNotifications($user)->latest()->limit(20)->get();

        return response()->json([
            'unread_count' => $this->visibleNotifications($user)->whereNull('read_at')->count(),
            'notifications' => $notifications->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Pembaruan POS',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? route('pos.index'),
                'created_at' => $notification->created_at?->toIso8601String(),
                'read_at' => $notification->read_at?->toIso8601String(),
            ]),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $this->visibleNotifications($request->user())->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['unread_count' => $this->visibleNotifications($request->user())->whereNull('read_at')->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->visibleNotifications($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    private function visibleNotifications(User $user): MorphMany
    {
        $query = $user->notifications();
        if ($user->role === 'manager') {
            return $query;
        }
        $categories = [];
        if (ModuleAccess::allows($user, 'pesanan')) {
            array_push($categories, 'orders', 'kitchen', 'sales');
        } elseif (ModuleAccess::allows($user, 'kasir')) {
            $categories[] = 'sales';
        }
        if (ModuleAccess::allows($user, 'kasir') || ModuleAccess::allows($user, 'cycle_count')) {
            $categories[] = 'shifts';
        }
        if (ModuleAccess::allows($user, 'stok')) {
            $categories[] = 'stock';
        }

        $legacyTitles = [];
        $titlesByCategory = [
            'orders' => ['Pesanan online masuk', 'Pesanan dibatalkan'],
            'sales' => ['Transaksi selesai', 'Pembayaran selesai'],
            'kitchen' => ['Pesanan siap disajikan'],
            'shifts' => ['Shift kasir dibuka', 'Shift kasir ditutup'],
        ];
        foreach ($categories as $category) {
            $legacyTitles = array_merge($legacyTitles, $titlesByCategory[$category] ?? []);
        }

        return $query->where(function ($builder) use ($categories, $legacyTitles): void {
            $builder->whereIn('data->category', $categories)
                ->orWhere(function ($legacy) use ($legacyTitles): void {
                    $legacy->whereNull('data->category')->whereIn('data->title', $legacyTitles);
                });
        });
    }
}
