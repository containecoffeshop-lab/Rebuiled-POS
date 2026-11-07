<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Services\PosNotificationService;
use App\Services\RecipeInventory;
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
        $notifications = $user->notifications()->latest()->limit(20)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
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
        $notification = $request->user()->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }
}
