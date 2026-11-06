<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockTake;
use App\Services\RecipeInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockTakeController extends Controller
{
    public function index(RecipeInventory $inventory): View
    {
        $materials = Material::query()->with('unit')->orderBy('name')->get();
        $stockOnHand = $inventory->onHandByMaterial($materials->pluck('id')->all());
        $materials->each(fn (Material $material) => $material->setAttribute('stock_on_hand', $stockOnHand->get($material->id, 0)));
        $stockTakes = StockTake::query()
            ->with(['user', 'details'])
            ->latest('checked_at')
            ->latest('id')
            ->paginate(15);

        return view('stock-takes.index', compact('materials', 'stockTakes'));
    }

    public function store(Request $request, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:3', 'max:50', 'unique:stock_takes,reference'],
            'checked_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.material_id' => ['required', 'integer', 'distinct', 'exists:materials,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $inventory, $request): void {
            $stockTake = StockTake::query()->create([
                'reference' => $data['reference'],
                'user_id' => $request->user()->id,
                'checked_at' => $data['checked_at'],
                'note' => $data['note'] ?? null,
            ]);
            $materials = Material::query()
                ->with('unit')
                ->whereIn('id', collect($data['items'])->pluck('material_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $item) {
                $material = $materials->get($item['material_id']);
                $countedMilli = (int) round((float) $item['counted_quantity'] * 1000);
                $systemMilli = $inventory->reconcilePhysicalCount($material->id, $countedMilli, $stockTake->reference);
                $differenceMilli = $countedMilli - $systemMilli;

                $stockTake->details()->create([
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit_name' => $material->unit->name,
                    'system_quantity' => number_format($systemMilli / 1000, 3, '.', ''),
                    'counted_quantity' => number_format($countedMilli / 1000, 3, '.', ''),
                    'difference' => number_format($differenceMilli / 1000, 3, '.', ''),
                    'note' => $item['note'] ?? null,
                ]);
            }
        }, attempts: 3);

        return back()->with('success', 'Stok opname disimpan dan penyesuaian dicatat sebagai mutasi stok.');
    }
}
