<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialStockBatch;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\MenuVariant;
use App\Models\MenuVariantRecipe;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeInventory
{
    public function availableServings(EloquentCollection $menus): Collection
    {
        $menus->loadMissing(['recipes', 'bundleItems.componentMenu.recipes']);
        $materialIds = $menus->flatMap(fn (Menu $menu) => array_keys($this->menuRequirements($menu)))->unique()->values()->all();
        $onHand = $this->onHandMilli($materialIds);

        return $menus->mapWithKeys(function (Menu $menu) use ($onHand): array {
            $requirements = $this->menuRequirements($menu);

            if ($requirements === []) {
                return [$menu->id => null];
            }

            $servings = collect($requirements)->map(fn (int $needed, int $materialId): int => intdiv($onHand->get($materialId, 0), $needed));

            return [$menu->id => $servings->min() ?? 0];
        });
    }

    public function availableVariantServings(EloquentCollection $menus): Collection
    {
        $menus->loadMissing(['recipes', 'variants.recipes', 'bundleItems.componentMenu.recipes']);
        $requirements = $menus->flatMap(fn (Menu $menu) => $menu->variants
            ->filter(fn (MenuVariant $variant) => $variant->is_active)
            ->mapWithKeys(fn (MenuVariant $variant): array => [$variant->id => $this->menuRequirements($menu, $variant)]));
        $materialIds = $requirements->flatMap(fn (array $rows): array => array_keys($rows))->unique()->values()->all();
        $onHand = $this->onHandMilli($materialIds);

        return $menus->flatMap(fn (Menu $menu) => $menu->variants
            ->filter(fn (MenuVariant $variant) => $variant->is_active)
            ->mapWithKeys(function (MenuVariant $variant) use ($requirements, $onHand): array {
                $rows = $requirements->get($variant->id, []);
                if ($rows === []) {
                    return [$variant->id => null];
                }

                $servings = collect($rows)->map(fn (int $needed, int $materialId): int => intdiv($onHand->get($materialId, 0), $needed));

                return [$variant->id => $servings->min() ?? 0];
            }));
    }

    public function onHandByMaterial(array $materialIds): Collection
    {
        return $this->onHandMilli($materialIds)->map(fn (int $quantity) => $quantity / 1000);
    }

    public function menuCostEstimates(EloquentCollection $menus): Collection
    {
        $menus->loadMissing(['recipes', 'bundleItems.componentMenu.recipes']);
        $requirements = $menus->mapWithKeys(fn (Menu $menu): array => [$menu->id => $this->menuRequirements($menu)]);
        $materialIds = $requirements->flatMap(fn (array $rows): array => array_keys($rows))->unique()->values()->all();
        $unitCosts = $this->materialUnitCostEstimates($materialIds);

        return $menus->mapWithKeys(function (Menu $menu) use ($requirements, $unitCosts): array {
            $menuRequirements = $requirements->get($menu->id, []);

            if ($menuRequirements === []) {
                return [$menu->id => ['cost' => null, 'margin_percent' => null]];
            }

            $cost = 0;
            foreach ($menuRequirements as $materialId => $quantityMilli) {
                $unitCost = $unitCosts->get($materialId);

                if ($unitCost === null) {
                    return [$menu->id => ['cost' => null, 'margin_percent' => null]];
                }

                $cost += (int) round($unitCost * ($quantityMilli / 1000));
            }

            return [$menu->id => [
                'cost' => $cost,
                'margin_percent' => $menu->selling_price > 0
                    ? round(($menu->selling_price - $cost) / $menu->selling_price * 100, 1)
                    : null,
            ]];
        });
    }

    public function materialUnitCostEstimates(array $materialIds): Collection
    {
        return $this->activeBatchBalances($materialIds)
            ->groupBy('material_id')
            ->map(function (Collection $materialBatches): ?float {
                $quantityMilli = $materialBatches->sum('on_hand_milli');

                if ($quantityMilli < 1 || $materialBatches->contains(fn (object $batch): bool => $batch->record->unit_cost === null)) {
                    return null;
                }

                $totalCost = $materialBatches->sum(fn (object $batch): int => (int) round(
                    (float) $batch->record->unit_cost * ($batch->on_hand_milli / 1000),
                ));

                return $totalCost * 1000 / $quantityMilli;
            });
    }

    public function receive(
        int $materialId,
        string $batchCode,
        int $quantityMilli,
        ?string $expiresAt,
        string $description = 'Stok masuk',
        ?string $unitCost = null,
    ): void {
        DB::transaction(function () use ($materialId, $batchCode, $quantityMilli, $expiresAt, $description, $unitCost): void {
            Material::query()->lockForUpdate()->findOrFail($materialId);
            $batch = MaterialStockBatch::create([
                'material_id' => $materialId,
                'batch_code' => $batchCode,
                'expires_at' => $expiresAt,
                'unit_cost' => $unitCost,
            ]);

            $batch->movements()->create([
                'type' => 'in',
                'quantity' => $this->fromMilli($quantityMilli),
                'description' => $description,
                'unit_cost' => $unitCost,
                'cost_amount' => $this->costForQuantity($unitCost, $quantityMilli),
            ]);
        }, attempts: 3);
    }

    public function removeFromBatch(int $batchId, int $quantityMilli, string $description): void
    {
        if ($quantityMilli < 1) {
            throw ValidationException::withMessages(['items' => 'Jumlah stok yang dikembalikan tidak valid.']);
        }

        DB::transaction(function () use ($batchId, $quantityMilli, $description): void {
            $batch = MaterialStockBatch::query()->findOrFail($batchId);
            Material::query()->whereKey($batch->material_id)->lockForUpdate()->firstOrFail();
            $batch = MaterialStockBatch::query()->whereKey($batchId)->lockForUpdate()->firstOrFail();
            $onHand = DB::table('stock_movements')
                ->where('material_stock_batch_id', $batch->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) AS on_hand")
                ->value('on_hand');
            $availableMilli = $this->toMilli($onHand);

            if ($availableMilli < $quantityMilli) {
                throw ValidationException::withMessages([
                    'items' => 'Stok pada batch '.$batch->batch_code.' tidak mencukupi untuk retur. Sisa batch: '
                        .number_format($availableMilli / 1000, 3, ',', '.').'.',
                ]);
            }

            $batch->movements()->create([
                'type' => 'out',
                'quantity' => $this->fromMilli($quantityMilli),
                'description' => $description,
                'unit_cost' => $batch->unit_cost,
                'cost_amount' => $this->costForQuantity($batch->unit_cost, $quantityMilli),
            ]);
        }, attempts: 3);
    }

    public function reconcilePhysicalCount(int $materialId, int $countedMilli, string $reference): int
    {
        if ($countedMilli < 0) {
            throw ValidationException::withMessages(['items' => 'Jumlah hasil opname tidak valid.']);
        }

        return DB::transaction(function () use ($materialId, $countedMilli, $reference): int {
            Material::query()->whereKey($materialId)->lockForUpdate()->firstOrFail();
            $batches = $this->activeBatchBalances([$materialId], lock: true);
            $systemMilli = $batches->sum('on_hand_milli');
            $differenceMilli = $countedMilli - $systemMilli;
            $description = 'Penyesuaian stok opname '.$reference;

            if ($differenceMilli > 0) {
                $batch = MaterialStockBatch::query()->create([
                    'material_id' => $materialId,
                    'batch_code' => substr('OPNAME-'.strtoupper($reference), 0, 64),
                ]);
                $batch->movements()->create([
                    'type' => 'in',
                    'quantity' => $this->fromMilli($differenceMilli),
                    'description' => $description,
                ]);
            } elseif ($differenceMilli < 0) {
                $remainingMilli = abs($differenceMilli);

                foreach ($batches as $batch) {
                    if ($remainingMilli <= 0) {
                        break;
                    }

                    $quantityMilli = min($remainingMilli, $batch->on_hand_milli);
                    $batch->record->movements()->create([
                        'type' => 'out',
                        'quantity' => $this->fromMilli($quantityMilli),
                        'description' => $description,
                        'unit_cost' => $batch->record->unit_cost,
                        'cost_amount' => $this->costForQuantity($batch->record->unit_cost, $quantityMilli),
                    ]);
                    $remainingMilli -= $quantityMilli;
                }

                if ($remainingMilli > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Stok berubah saat opname. Segarkan data dan ulangi pencatatan.',
                    ]);
                }
            }

            return $systemMilli;
        }, attempts: 3);
    }

    public function assertAvailable(array $items): void
    {
        $items = collect($items);
        $menuIds = $items->pluck('menu_id')->unique();
        $variantIds = $items->pluck('menu_variant_id')->filter()->unique();
        $menus = Menu::query()
            ->whereIn('id', $menuIds)
            ->with(['recipes', 'variants.recipes', 'bundleItems.componentMenu.recipes'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $variants = MenuVariant::query()->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        MenuVariantRecipe::query()
            ->whereIn('menu_variant_id', $variantIds)
            ->orderBy('menu_variant_id')
            ->orderBy('material_id')
            ->lockForUpdate()
            ->get();
        $sourceMenuIds = $menus->flatMap(fn (Menu $menu) => $menu->is_bundle
            ? $menu->bundleItems->pluck('component_menu_id')
            : collect([$menu->id]));
        MenuRecipe::query()
            ->whereIn('menu_id', $sourceMenuIds->unique())
            ->orderBy('material_id')
            ->lockForUpdate()
            ->get();
        $requirements = collect();

        foreach ($items as $item) {
            $menu = $menus->firstWhere('id', (int) $item['menu_id']);
            $variant = filled($item['menu_variant_id'] ?? null) ? $variants->get((int) $item['menu_variant_id']) : null;

            if (! $menu || (filled($item['menu_variant_id'] ?? null)
                && (! $variant || $variant->menu_id !== $menu->id || ! $variant->is_active || $menu->is_bundle))) {
                throw ValidationException::withMessages(['items' => 'Varian menu tidak valid atau sudah tidak tersedia.']);
            }

            foreach ($this->menuRequirements($menu, $variant) as $materialId => $quantityMilli) {
                $requirements->put($materialId, $requirements->get($materialId, 0) + $quantityMilli * (int) $item['quantity']);
            }
        }

        $this->assertMaterialAvailability($requirements);
    }

    public function snapshot(Transaction $transaction): void
    {
        $transaction->load([
            'details.menu.recipes.material.unit',
            'details.menu.bundleItems.componentMenu.recipes.material.unit',
            'details.menuVariant.recipes.material.unit',
        ]);

        foreach ($transaction->details as $detail) {
            $recipeRequirements = collect();

            foreach ($this->expandedRecipes($detail->menu, $detail->menuVariant) as [$recipe, $componentQuantity]) {
                $quantityMilli = $this->toMilli($recipe->quantity) * $componentQuantity * $detail->quantity;
                $requirement = $recipeRequirements->get($recipe->material_id, [
                    'recipe' => $recipe,
                    'quantity_milli' => 0,
                ]);
                $requirement['quantity_milli'] += $quantityMilli;
                $recipeRequirements->put($recipe->material_id, $requirement);
            }

            foreach ($recipeRequirements as $requirement) {
                $recipe = $requirement['recipe'];
                $detail->recipeDetails()->create([
                    'material_id' => $recipe->material_id,
                    'material_name' => $recipe->material->name,
                    'unit_name' => $recipe->material->unit->name,
                    'quantity' => $this->fromMilli($requirement['quantity_milli']),
                ]);
            }
        }
    }

    public function consume(Transaction $transaction): array
    {
        $details = $transaction->details()
            ->whereNotNull('menu_id')
            ->with('recipeDetails')
            ->get();

        if ($details->isEmpty()) {
            return ['total' => 0, 'complete' => true];
        }

        $requirements = $details
            ->flatMap(fn ($detail) => $detail->recipeDetails->map(fn ($recipe) => [
                'material_id' => $recipe->material_id,
                'quantity_milli' => $this->toMilli($recipe->quantity),
            ]))
            ->groupBy('material_id')
            ->map(fn (Collection $rows) => $rows->sum('quantity_milli'));

        if ($requirements->isEmpty()) {
            return ['total' => 0, 'complete' => false];
        }

        $batches = $this->assertMaterialAvailability($requirements);
        $cogsComplete = $details->every(fn ($detail) => $detail->recipeDetails->isNotEmpty());
        $cogsTotal = 0;

        foreach ($requirements as $materialId => $required) {
            $remaining = $required;

            foreach ($batches->get($materialId, collect()) as $batch) {
                if ($remaining <= 0) {
                    break;
                }
                $quantity = min($remaining, $batch->on_hand_milli);
                $unitCost = $batch->record->unit_cost;
                $costAmount = $this->costForQuantity($unitCost, $quantity);
                $batch->record->movements()->create([
                    'transaction_id' => $transaction->id,
                    'type' => 'out',
                    'quantity' => $this->fromMilli($quantity),
                    'description' => 'Transaksi '.$transaction->invoice,
                    'unit_cost' => $unitCost,
                    'cost_amount' => $costAmount,
                ]);
                if ($costAmount === null) {
                    $cogsComplete = false;
                } else {
                    $cogsTotal += $costAmount;
                }
                $remaining -= $quantity;
            }
        }

        return ['total' => $cogsTotal, 'complete' => $cogsComplete];
    }

    private function assertMaterialAvailability(Collection $requirements): Collection
    {
        if ($requirements->isEmpty()) {
            return collect();
        }

        $materialIds = $requirements->keys()->sort()->values()->all();
        $materials = Material::query()->with('unit')->whereIn('id', $materialIds)->get()->keyBy('id');
        Material::query()->whereIn('id', $materialIds)->orderBy('id')->lockForUpdate()->get();
        $batches = $this->activeBatchBalances($materialIds, lock: true)->groupBy('material_id');
        $shortages = [];

        foreach ($requirements as $materialId => $required) {
            $available = $batches->get($materialId, collect())->sum('on_hand_milli');

            if ($available < $required) {
                $material = $materials->get($materialId);
                $shortages[] = ($material?->name ?? 'Bahan #'.$materialId)
                    .' kurang '.number_format(($required - $available) / 1000, 3, ',', '.')
                    .' '.($material?->unit?->name ?? '');
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'items' => 'Stok bahan tidak mencukupi: '.implode('; ', $shortages).'. Tambahkan stok atau perbarui resep.',
            ]);
        }

        return $batches;
    }

    private function onHandMilli(array $materialIds): Collection
    {
        if ($materialIds === []) {
            return collect();
        }

        return $this->activeBatchBalances($materialIds)
            ->groupBy('material_id')
            ->map(fn (Collection $batches) => $batches->sum('on_hand_milli'));
    }

    private function menuRequirements(Menu $menu, ?MenuVariant $variant = null): array
    {
        $requirements = [];

        foreach ($this->expandedRecipes($menu, $variant) as [$recipe, $componentQuantity]) {
            $requirements[$recipe->material_id] = ($requirements[$recipe->material_id] ?? 0)
                + $this->toMilli($recipe->quantity) * $componentQuantity;
        }

        return $requirements;
    }

    private function expandedRecipes(?Menu $menu, ?MenuVariant $variant = null): array
    {
        if (! $menu) {
            return [];
        }

        $components = $menu->is_bundle
            ? $menu->bundleItems->map(fn ($item) => [$item->componentMenu, 1])
            : collect([[$menu, 1]]);

        $recipes = $components
            ->filter(fn (array $component) => $component[0] instanceof Menu)
            ->flatMap(fn (array $component) => $component[0]->recipes->map(fn (MenuRecipe $recipe) => [$recipe, $component[1]]))
            ->all();

        if ($variant && ! $menu->is_bundle) {
            array_push(
                $recipes,
                ...$variant->recipes->map(fn (MenuVariantRecipe $recipe) => [$recipe, 1])->all(),
            );
        }

        return $recipes;
    }

    private function activeBatchBalances(array $materialIds, bool $lock = false): Collection
    {
        if ($materialIds === []) {
            return collect();
        }

        $query = MaterialStockBatch::query()
            ->whereIn('material_id', $materialIds)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->select(['id', 'material_id', 'batch_code', 'expires_at', 'unit_cost']);

        if ($lock) {
            $query->orderBy('material_id')
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id');
            $query->lockForUpdate();
        }

        $batches = $query->get();
        $movementBalances = DB::table('stock_movements')
            ->whereIn('material_stock_batch_id', $batches->pluck('id'))
            ->select('material_stock_batch_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS on_hand")
            ->groupBy('material_stock_batch_id')
            ->pluck('on_hand', 'material_stock_batch_id');

        return $batches
            ->map(fn ($batch) => (object) [
                'id' => $batch->id,
                'material_id' => $batch->material_id,
                'batch_code' => $batch->batch_code,
                'expires_at' => $batch->expires_at,
                'on_hand_milli' => $this->toMilli($movementBalances->get($batch->id, 0)),
                'record' => $batch,
            ])
            ->filter(fn ($batch) => $batch->on_hand_milli > 0)
            ->values();
    }

    private function toMilli(string|int|float $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function fromMilli(int $quantity): string
    {
        return number_format($quantity / 1000, 3, '.', '');
    }

    private function costForQuantity(?string $unitCost, int $quantityMilli): ?int
    {
        return $unitCost === null ? null : (int) round((float) $unitCost * ($quantityMilli / 1000));
    }
}
