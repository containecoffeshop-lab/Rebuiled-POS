<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\CycleCountTask;
use App\Models\Material;
use App\Models\Transaction;
use App\Services\RecipeInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CycleCountController extends Controller
{
    public function index(Request $request): View
    {
        $shift = CashierShift::query()->where('user_id', $request->user()->id)->whereNull('closed_at')->first();
        $tasks = CycleCountTask::query()->with(['items', 'creator', 'counter', 'approver'])
            ->when($request->user()->role !== 'manager', fn ($query) => $query->where('status', 'pending')->where('shift_number', $shift?->shift_number))
            ->latest('id')->paginate(15);
        $materials = $request->user()->role === 'manager' ? Material::query()->with('unit')->orderBy('name')->get() : collect();
        $expectedCash = $shift ? $shift->starting_cash + (int) Transaction::query()->where('cashier_shift_id', $shift->id)->where('status', 'paid')->where('payment_method', 'cash')->sum('grand_total') : null;

        return view('cycle-counts.index', compact('tasks', 'materials', 'shift', 'expectedCash'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'task_date' => ['required', 'date_format:Y-m-d'],
            'shift_number' => ['required', 'integer', 'in:1,2'],
            'note' => ['nullable', 'string', 'max:1000'],
            'materials' => ['required', 'array', 'min:1', 'max:100'],
            'materials.*' => ['required', 'integer', 'distinct', 'exists:materials,id'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $materials = Material::query()->with('unit')->whereIn('id', $data['materials'])->orderBy('id')->lockForUpdate()->get();
            $task = CycleCountTask::create([
                'task_date' => $data['task_date'],
                'shift_number' => $data['shift_number'],
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($materials as $material) {
                $task->items()->create(['material_id' => $material->id, 'material_name' => $material->name, 'unit_name' => $material->unit->name]);
            }
        }, attempts: 3);

        return back()->with('success', 'Tugas cycle count dibuat.');
    }

    public function count(Request $request, CycleCountTask $task, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($task, $data, $request, $inventory): void {
            $locked = CycleCountTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['items' => 'Tugas sudah diisi atau diselesaikan.']);
            }
            $shift = CashierShift::query()->where('user_id', $request->user()->id)->whereNull('closed_at')->lockForUpdate()->first();
            if (! $shift || $shift->shift_number !== $locked->shift_number) {
                throw ValidationException::withMessages(['items' => 'Buka shift kasir aktif sesuai tugas sebelum menghitung.']);
            }
            $items = $locked->items()->orderBy('material_id')->lockForUpdate()->get()->keyBy('id');
            $submitted = collect($data['items'])->keyBy('id');
            if ($items->count() !== $submitted->count() || $items->keys()->diff($submitted->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Semua bahan tugas harus dihitung tepat satu kali.']);
            }
            Material::query()->whereIn('id', $items->pluck('material_id'))->orderBy('id')->lockForUpdate()->get();
            $stock = $inventory->onHandByMaterial($items->pluck('material_id')->all());
            foreach ($items as $item) {
                $count = $submitted->get($item->id);
                $systemMilli = (int) round((float) $stock->get($item->material_id, 0) * 1000);
                $countedMilli = (int) round((float) $count['quantity'] * 1000);
                $item->update([
                    'system_quantity' => number_format($systemMilli / 1000, 3, '.', ''),
                    'counted_quantity' => number_format($countedMilli / 1000, 3, '.', ''),
                    'difference' => number_format(($countedMilli - $systemMilli) / 1000, 3, '.', ''),
                    'note' => $count['note'] ?? null,
                ]);
            }
            $locked->update(['status' => 'review', 'counted_by' => $request->user()->id, 'cashier_shift_id' => $shift->id, 'counted_at' => now()]);
        }, attempts: 3);

        return back()->with('success', 'Hitungan dikirim untuk persetujuan pengelola.');
    }

    public function approve(Request $request, CycleCountTask $task, RecipeInventory $inventory): RedirectResponse
    {
        DB::transaction(function () use ($task, $request, $inventory): void {
            $locked = CycleCountTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($locked->status !== 'review') {
                throw ValidationException::withMessages(['task' => 'Tugas tidak menunggu persetujuan.']);
            }
            $items = $locked->items()->orderBy('material_id')->lockForUpdate()->get();
            Material::query()->whereIn('id', $items->pluck('material_id'))->orderBy('id')->lockForUpdate()->get();
            $stock = $inventory->onHandByMaterial($items->pluck('material_id')->all());
            foreach ($items as $item) {
                $before = (int) round((float) $stock->get($item->material_id, 0) * 1000);
                $difference = (int) round((float) $item->difference * 1000);
                $after = $before + $difference;
                if ($after < 0 || $after > 999999999999) {
                    throw ValidationException::withMessages(['task' => 'Penyesuaian '.$item->material_name.' menghasilkan stok negatif atau melebihi batas. Periksa stok dan lakukan hitung ulang.']);
                }
                $inventory->reconcilePhysicalCount($item->material_id, $after, 'CYCLE-'.$locked->id);
                $item->update([
                    'stock_before_approval' => number_format($before / 1000, 3, '.', ''),
                    'stock_after_approval' => number_format($after / 1000, 3, '.', ''),
                ]);
            }
            $locked->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        }, attempts: 3);

        return back()->with('success', 'Tugas disetujui dan selisih diterapkan ke stok terkini.');
    }

    public function returnForRecount(CycleCountTask $task): RedirectResponse
    {
        DB::transaction(function () use ($task): void {
            $locked = CycleCountTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($locked->status !== 'review') {
                throw ValidationException::withMessages(['task' => 'Tugas tidak menunggu persetujuan.']);
            }
            $locked->update(['status' => 'pending', 'counted_by' => null, 'cashier_shift_id' => null, 'counted_at' => null]);
        }, attempts: 3);

        return back()->with('success', 'Tugas dikembalikan untuk dihitung ulang.');
    }
}
