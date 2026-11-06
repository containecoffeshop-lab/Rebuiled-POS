<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\ProductPromotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductPromotionController extends Controller
{
    public function index(): View
    {
        $promotions = ProductPromotion::query()->with(['menus:id,name', 'customers:id,name'])->latest()->paginate(15);

        return view('promotions.index', [
            'promotions' => $promotions,
            'menus' => Menu::query()->where('is_available', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $promotion = DB::transaction(function () use ($data): ProductPromotion {
            $promotion = ProductPromotion::create([
                'name' => $data['name'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'minimum_quantity' => $data['minimum_quantity'],
                'all_menus' => $data['all_menus'],
                'all_customers' => $data['all_customers'],
                'is_active' => true,
            ]);
            $promotion->menus()->sync($data['menu_ids'] ?? []);
            $promotion->customers()->sync($data['customer_ids'] ?? []);

            return $promotion;
        });

        return back()->with('success', 'Promo '.$promotion->name.' berhasil dibuat.');
    }

    public function toggle(ProductPromotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', 'Status promo '.$promotion->name.' diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'minimum_quantity' => ['required', 'integer', 'min:1', 'max:600'],
            'all_menus' => ['required', 'boolean'],
            'all_customers' => ['required', 'boolean'],
            'menu_ids' => ['nullable', 'array', 'max:200'],
            'menu_ids.*' => ['integer', 'distinct', 'exists:menus,id'],
            'customer_ids' => ['nullable', 'array', 'max:1000'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
        ]);
        $data['all_menus'] = $request->boolean('all_menus');
        $data['all_customers'] = $request->boolean('all_customers');

        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Persentase diskon maksimal 100%.']);
        }

        if (! $data['all_menus'] && empty($data['menu_ids'])) {
            throw ValidationException::withMessages(['menu_ids' => 'Pilih setidaknya satu menu atau aktifkan semua menu.']);
        }

        if (! $data['all_customers'] && empty($data['customer_ids'])) {
            throw ValidationException::withMessages(['customer_ids' => 'Pilih setidaknya satu pelanggan atau aktifkan semua pelanggan.']);
        }

        return $data;
    }
}
