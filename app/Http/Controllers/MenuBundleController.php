<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuBundleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuBundleController extends Controller
{
    public function index(): View
    {
        return view('bundles.index', [
            'bundles' => Menu::query()->where('is_bundle', true)->with('category', 'bundleItems.componentMenu')->orderBy('name')->paginate(15),
            'categories' => Category::query()->orderBy('name')->get(),
            'menus' => Menu::query()->where('is_bundle', false)->where('is_available', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $bundle = $this->save($request);

        return back()->with('success', 'Paket '.$bundle->name.' berhasil dibuat.');
    }

    public function update(Request $request, Menu $bundle): RedirectResponse
    {
        abort_unless($bundle->is_bundle, 404);
        $bundle = $this->save($request, $bundle);

        return back()->with('success', 'Perubahan paket '.$bundle->name.' berhasil disimpan.');
    }

    private function save(Request $request, ?Menu $bundle = null): Menu
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'selling_price' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'description' => ['nullable', 'string', 'max:500'],
            'component_ids' => ['required', 'array', 'min:2', 'max:20'],
            'component_ids.*' => ['required', 'integer', 'distinct', 'exists:menus,id'],
        ]);

        return DB::transaction(function () use ($data, $bundle): Menu {
            $components = Menu::query()
                ->whereIn('id', $data['component_ids'])
                ->where('is_bundle', false)
                ->where('is_available', true)
                ->lockForUpdate()
                ->get();

            if ($components->count() !== count($data['component_ids'])) {
                throw ValidationException::withMessages(['component_ids' => 'Semua komponen harus berupa menu aktif dan bukan paket lain.']);
            }

            if ((int) $data['selling_price'] >= $components->sum('selling_price')) {
                throw ValidationException::withMessages(['selling_price' => 'Harga paket harus lebih rendah dari jumlah harga menu komponennya.']);
            }

            $values = [
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'selling_price' => $data['selling_price'],
                'is_bundle' => true,
            ];
            $bundle ??= new Menu(['is_available' => true]);
            $bundle->fill($values)->save();

            MenuBundleItem::query()->where('bundle_menu_id', $bundle->id)->delete();
            $bundle->bundleItems()->createMany($components->map(fn (Menu $menu) => [
                'component_menu_id' => $menu->id,
            ])->all());

            return $bundle;
        }, attempts: 3);
    }
}
