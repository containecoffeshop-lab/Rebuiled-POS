<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\ProductPromotion;
use Illuminate\Support\Collection;

class ProductPromotionCalculator
{
    public function calculate(array $items, ?int $customerId): array
    {
        $promotions = ProductPromotion::query()
            ->where('is_active', true)
            ->with(['menus:id', 'customers:id'])
            ->orderBy('id')
            ->get();
        $result = [];

        foreach ($items as $item) {
            /** @var Menu $menu */
            $menu = $item['menu'];
            $key = $item['key'] ?? $menu->id;
            $result[$key] = ['discount' => 0, 'name' => null];
        }

        foreach ($promotions as $promotion) {
            if (! $promotion->all_customers
                && (! $customerId || ! $promotion->customers->contains('id', $customerId))) {
                continue;
            }

            $eligible = collect($items)->filter(fn (array $item): bool => $promotion->all_menus
                || $promotion->menus->contains('id', $item['menu']->id));

            if ($eligible->sum('quantity') < $promotion->minimum_quantity) {
                continue;
            }

            foreach ($eligible as $item) {
                /** @var Menu $menu */
                $menu = $item['menu'];
                $key = $item['key'] ?? $menu->id;
                $price = (int) ($item['price'] ?? $menu->selling_price);
                $unitDiscount = $promotion->discount_type === 'percentage'
                    ? (int) floor($price * $promotion->discount_value / 100)
                    : min($promotion->discount_value, $price);
                $discount = $unitDiscount * $item['quantity'];

                if ($discount > $result[$key]['discount']) {
                    $result[$key] = ['discount' => $discount, 'name' => $promotion->name];
                }
            }
        }

        return $result;
    }

    public function summarize(Collection $lines, array $promotions): array
    {
        $discount = $lines->sum(fn (array $line): int => (int) ($promotions[$line['key'] ?? $line['menu']->id]['discount'] ?? 0));

        return [
            'discount' => $discount,
            'lines' => $lines->map(fn (array $line): array => [
                'menu_id' => $line['menu']->id,
                'discount' => (int) ($promotions[$line['key'] ?? $line['menu']->id]['discount'] ?? 0),
                'name' => $promotions[$line['key'] ?? $line['menu']->id]['name'] ?? null,
            ])->values()->all(),
        ];
    }
}
