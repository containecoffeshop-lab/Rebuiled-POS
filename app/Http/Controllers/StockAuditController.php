<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\StockAuditTask;
use App\Services\RecipeInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockAuditController extends Controller
{
    public function index(RecipeInventory $inventory): View
    {
        $materials = Material::query()->with('unit')->orderBy('name')->get();
        $stockOnHand = $inventory->onHandByMaterial($materials->pluck('id')->all());
        $materials->each(fn (Material $material) => $material->setAttribute('stock_on_hand', $stockOnHand->get($material->id, 0)));

        return view('stock-audits.index', [
            'materials' => $materials,
            'pendingTask' => StockAuditTask::query()
                ->where('status', 'awaiting_shift2')
                ->with(['shift1User', 'items'])
                ->latest('id')
                ->first(),
            'tasks' => StockAuditTask::query()
                ->with(['shift1User', 'shift2User', 'items'])
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function storeShiftOne(Request $request, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.material_id' => ['required', 'integer', 'distinct', 'exists:materials,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $task = DB::transaction(function () use ($data, $inventory, $request): StockAuditTask {
            $hasPendingTask = StockAuditTask::query()
                ->where('status', 'awaiting_shift2')
                ->lockForUpdate()
                ->exists();
            if ($hasPendingTask) {
                throw ValidationException::withMessages(['items' => 'Selesaikan tugas Shift 2 yang tertunda sebelum memulai Audit Stock baru.']);
            }

            $allMaterialIds = Material::query()->orderBy('id')->pluck('id')->all();
            $countedMaterialIds = collect($data['items'])->pluck('material_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($countedMaterialIds !== collect($allMaterialIds)->map(fn ($id): int => (int) $id)->values()->all()) {
                throw ValidationException::withMessages(['items' => 'Audit harus mencakup semua bahan agar saldo stok dapat direkonsiliasi lengkap.']);
            }

            $materials = Material::query()
                ->with('unit')
                ->whereIn('id', collect($data['items'])->pluck('material_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $task = StockAuditTask::query()->create([
                'task_code' => 'TASK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'shift1_user_id' => $request->user()->id,
                'status' => 'awaiting_shift2',
                'note' => $data['note'] ?? null,
                'shift1_completed_at' => now(),
            ]);

            $onHand = $inventory->onHandByMaterial($materials->keys()->all());
            foreach ($data['items'] as $item) {
                $material = $materials->get((int) $item['material_id']);
                $systemMilli = (int) round((float) $onHand->get($material->id, 0) * 1000);
                $countedMilli = (int) round((float) $item['counted_quantity'] * 1000);
                $task->items()->create([
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit_name' => $material->unit->name,
                    'system_quantity' => number_format($systemMilli / 1000, 3, '.', ''),
                    'shift1_quantity' => number_format($countedMilli / 1000, 3, '.', ''),
                    'shift1_note' => $item['note'] ?? null,
                ]);
            }

            return $task;
        }, attempts: 3);

        return redirect()->route('pos.stock-audits.index')
            ->with('success', $task->task_code.' Shift 1 selesai. Serahkan kode TASK kepada Shift 2 untuk hitung independen.');
    }

    public function storeShiftTwo(Request $request, StockAuditTask $stockAuditTask): RedirectResponse
    {
        $data = $request->validate([
            'task_code' => ['required', 'string', 'max:32'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request, $stockAuditTask): void {
            $task = StockAuditTask::query()->lockForUpdate()->findOrFail($stockAuditTask->id);
            if ($task->status !== 'awaiting_shift2') {
                throw ValidationException::withMessages(['items' => 'Tugas ini sudah selesai atau tidak menunggu Shift 2.']);
            }
            if (! hash_equals($task->task_code, $data['task_code'])) {
                throw ValidationException::withMessages(['task_code' => 'Kode TASK tidak cocok.']);
            }
            if ($task->shift1_user_id === $request->user()->id) {
                throw ValidationException::withMessages(['items' => 'Shift 2 harus dilakukan oleh akun berbeda untuk menjaga hitungan independen.']);
            }

            $items = $task->items()->lockForUpdate()->get()->keyBy('id');
            if (count($data['items']) !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'Shift 2 harus menghitung semua bahan yang terdaftar pada tugas ini.']);
            }

            foreach ($data['items'] as $count) {
                $item = $items->get((int) $count['item_id']);
                if (! $item || $item->shift2_quantity !== null) {
                    throw ValidationException::withMessages(['items' => 'Daftar hitungan Shift 2 tidak sesuai dengan tugas ini.']);
                }
                $item->update([
                    'shift2_quantity' => number_format((float) $count['counted_quantity'], 3, '.', ''),
                    'shift2_note' => $count['note'] ?? null,
                ]);
            }

            $task->update([
                'shift2_user_id' => $request->user()->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }, attempts: 3);

        return redirect()->route('pos.stock-audits.index')
            ->with('success', $stockAuditTask->task_code.' selesai. Selisih Shift 1, Shift 2, dan stok sistem tersedia pada riwayat; stok belum disesuaikan.');
    }
}
