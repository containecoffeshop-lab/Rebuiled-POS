<?php

namespace App\Http\Controllers;

use App\Models\BarcodeTemplate;
use App\Models\Menu;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BarcodeTemplateController extends Controller
{
    public function index(): View
    {
        $templates = BarcodeTemplate::query()->orderBy('name')->get();
        $menus = Menu::query()->availableForSale()->whereNotNull('barcode')->orderBy('name')->get();
        $productVariants = ProductVariant::query()
            ->with('product')
            ->where('is_available', true)
            ->whereNotNull('barcode')
            ->whereHas('product', fn ($query) => $query->where('is_available', true))
            ->orderBy('product_id')
            ->orderBy('label')
            ->get();

        return view('settings.barcode-templates', compact('templates', 'menus', 'productVariants'));
    }

    public function store(Request $request): RedirectResponse
    {
        BarcodeTemplate::query()->create($this->validatedTemplate($request));

        return back()->with('success', 'Template label berhasil ditambahkan.');
    }

    public function update(Request $request, BarcodeTemplate $barcodeTemplate): RedirectResponse
    {
        $barcodeTemplate->update($this->validatedTemplate($request, $barcodeTemplate));

        return back()->with('success', 'Template label berhasil diperbarui.');
    }

    public function destroy(BarcodeTemplate $barcodeTemplate): RedirectResponse
    {
        $barcodeTemplate->delete();

        return back()->with('success', 'Template label berhasil dihapus.');
    }

    public function print(Request $request): View
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:barcode_templates,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item' => ['nullable', 'string', 'regex:/^(menu|product):[1-9]\d*$/'],
            'items.*.menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $items = collect($data['items'])->map(function (array $item, int $index): array {
            if (filled($item['item'] ?? null)) {
                [$type, $id] = explode(':', $item['item'], 2);

                return ['type' => $type, 'id' => (int) $id, 'quantity' => (int) $item['quantity']];
            }

            $hasMenu = filled($item['menu_id'] ?? null);
            $hasVariant = filled($item['product_variant_id'] ?? null);

            if ($hasMenu === $hasVariant) {
                throw ValidationException::withMessages(["items.$index" => 'Pilih tepat satu menu atau varian produk.']);
            }

            return [
                'type' => $hasMenu ? 'menu' : 'product',
                'id' => (int) ($hasMenu ? $item['menu_id'] : $item['product_variant_id']),
                'quantity' => (int) $item['quantity'],
            ];
        });
        $keys = $items->map(fn (array $item): string => $item['type'].':'.$item['id']);

        if ($keys->unique()->count() !== $keys->count()) {
            throw ValidationException::withMessages(['items' => 'Item barcode yang sama tidak boleh dimasukkan dua kali.']);
        }

        $menus = Menu::query()
            ->availableForSale()
            ->whereIn('id', $items->where('type', 'menu')->pluck('id'))
            ->whereNotNull('barcode')
            ->get()
            ->keyBy('id');
        $productVariants = ProductVariant::query()
            ->with('product')
            ->where('is_available', true)
            ->whereHas('product', fn ($query) => $query->where('is_available', true))
            ->whereIn('id', $items->where('type', 'product')->pluck('id'))
            ->whereNotNull('barcode')
            ->get()
            ->keyBy('id');

        if ($menus->count() !== $items->where('type', 'menu')->count() || $productVariants->count() !== $items->where('type', 'product')->count()) {
            throw ValidationException::withMessages([
                'items' => 'Pilih menu atau produk tersedia yang telah memiliki barcode.',
            ]);
        }

        $labels = $items->flatMap(function (array $item) use ($menus, $productVariants): array {
            $label = $item['type'] === 'menu' ? $menus->get($item['id']) : $productVariants->get($item['id']);

            if ($item['type'] === 'product') {
                $label->setAttribute('name', $label->product->name.' · '.$label->label);
            }

            return array_fill(0, $item['quantity'], $label);
        });

        if ($labels->count() > 500) {
            throw ValidationException::withMessages(['items' => 'Maksimal 500 label dapat dicetak sekaligus.']);
        }

        $template = BarcodeTemplate::query()->findOrFail($data['template_id']);
        $capacity = $template->paper_type === 'sheet' ? $template->columns * $template->rows : 1;
        $pages = $labels->chunk($capacity);

        return view('settings.barcode-print', compact('template', 'pages'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTemplate(Request $request, ?BarcodeTemplate $template = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('barcode_templates', 'name')->ignore($template?->id)],
            'paper_type' => ['required', Rule::in(['roll', 'sheet'])],
            'paper_width_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'paper_height_mm' => [Rule::requiredIf($request->input('paper_type') === 'sheet'), 'nullable', 'numeric', 'min:10', 'max:500'],
            'label_width_mm' => ['required', 'numeric', 'min:5', 'max:250'],
            'label_height_mm' => ['required', 'numeric', 'min:5', 'max:250'],
            'gap_horizontal_mm' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'gap_vertical_mm' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'margin_top_mm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_left_mm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'columns' => ['required', 'integer', 'min:1', 'max:12'],
            'rows' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $data['gap_horizontal_mm'] = $data['gap_horizontal_mm'] ?? 0;
        $data['gap_vertical_mm'] = $data['gap_vertical_mm'] ?? 0;
        $data['margin_top_mm'] = $data['margin_top_mm'] ?? 0;
        $data['margin_left_mm'] = $data['margin_left_mm'] ?? 0;

        if ($data['paper_type'] === 'roll') {
            $data['paper_height_mm'] = null;
            $data['columns'] = 1;
            $data['rows'] = 1;

            if ($data['paper_width_mm'] < $data['label_width_mm'] + 2 * $data['margin_left_mm']) {
                throw ValidationException::withMessages(['label_width_mm' => 'Lebar label dan margin melebihi lebar kertas roll.']);
            }
        } elseif (
            $data['paper_width_mm'] < $data['label_width_mm'] * $data['columns'] + $data['gap_horizontal_mm'] * ($data['columns'] - 1) + 2 * $data['margin_left_mm']
            || $data['paper_height_mm'] < $data['label_height_mm'] * $data['rows'] + $data['gap_vertical_mm'] * ($data['rows'] - 1) + 2 * $data['margin_top_mm']
        ) {
            throw ValidationException::withMessages(['paper_width_mm' => 'Ukuran label, jarak, dan margin melebihi ukuran kertas.']);
        }

        return $data;
    }
}
