@extends('layouts.app')

@section('title', 'Template barcode')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pengelola</p><h1>Label barcode</h1><p>Buat tata letak sesuai ukuran kertas, lalu pilih menu ber-barcode untuk dicetak.</p></div>
    <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Tambah template</h2><p>Gunakan ukuran dalam milimeter. Template roll mencetak satu label per halaman.</p></div></div>
    <form class="pos-order-form" method="POST" action="{{ route('pos.barcode-templates.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field-group"><label class="field-label" for="template-name">Nama template</label><input class="field" id="template-name" name="name" maxlength="100" required></div>
            <div class="field-group"><label class="field-label" for="paper-type">Jenis kertas</label><select class="field" id="paper-type" name="paper_type"><option value="sheet">Lembar</option><option value="roll">Roll</option></select></div>
            <div class="field-group"><label class="field-label" for="paper-width">Lebar kertas (mm)</label><input class="field" id="paper-width" name="paper_width_mm" type="number" min="10" max="500" step="0.1" value="100" required></div>
            <div class="field-group" data-sheet-field><label class="field-label" for="paper-height">Tinggi kertas (mm)</label><input class="field" id="paper-height" name="paper_height_mm" type="number" min="10" max="500" step="0.1" value="150"></div>
            <div class="field-group"><label class="field-label" for="label-width">Lebar label (mm)</label><input class="field" id="label-width" name="label_width_mm" type="number" min="5" max="250" step="0.1" value="48" required></div>
            <div class="field-group"><label class="field-label" for="label-height">Tinggi label (mm)</label><input class="field" id="label-height" name="label_height_mm" type="number" min="5" max="250" step="0.1" value="30" required></div>
            <div class="field-group" data-sheet-field><label class="field-label" for="template-columns">Kolom</label><input class="field" id="template-columns" name="columns" type="number" min="1" max="12" value="2" required></div>
            <div class="field-group" data-sheet-field><label class="field-label" for="template-rows">Baris</label><input class="field" id="template-rows" name="rows" type="number" min="1" max="50" value="5" required></div>
            <div class="field-group"><label class="field-label" for="template-horizontal-gap">Jarak kolom (mm)</label><input class="field" id="template-horizontal-gap" name="gap_horizontal_mm" type="number" min="0" max="40" step="0.1" value="2"></div>
            <div class="field-group"><label class="field-label" for="template-vertical-gap">Jarak baris (mm)</label><input class="field" id="template-vertical-gap" name="gap_vertical_mm" type="number" min="0" max="40" step="0.1" value="2"></div>
            <div class="field-group"><label class="field-label" for="template-margin-left">Margin kiri/kanan (mm)</label><input class="field" id="template-margin-left" name="margin_left_mm" type="number" min="0" max="100" step="0.1" value="2"></div>
            <div class="field-group"><label class="field-label" for="template-margin-top">Margin atas/bawah (mm)</label><input class="field" id="template-margin-top" name="margin_top_mm" type="number" min="0" max="100" step="0.1" value="2"></div>
        </div>
        <button class="button" type="submit">Simpan template</button>
    </form>
</section>

@if ($templates->isNotEmpty())
<section class="panel">
    <div class="panel-head"><div><h2>Template tersimpan</h2><p>Perubahan ukuran dipakai untuk pencetakan berikutnya.</p></div></div>
    @foreach ($templates as $template)
        <details style="padding:14px 0;border-bottom:1px solid #dfe5dc">
            <summary class="nav-link" style="cursor:pointer">{{ $template->name }} · {{ $template->paper_type === 'roll' ? 'Roll' : 'Lembar' }} {{ $template->paper_width_mm }}{{ $template->paper_height_mm ? ' × '.$template->paper_height_mm : '' }} mm</summary>
            <form class="pos-order-form" method="POST" action="{{ route('pos.barcode-templates.update', $template) }}" style="margin-top:14px">
                @csrf @method('PATCH')
                <div class="form-grid">
                    <div class="field-group"><label class="field-label" for="edit-template-name-{{ $template->id }}">Nama</label><input class="field" id="edit-template-name-{{ $template->id }}" name="name" value="{{ $template->name }}" maxlength="100" required></div>
                    <div class="field-group"><label class="field-label" for="edit-paper-type-{{ $template->id }}">Jenis kertas</label><select class="field" id="edit-paper-type-{{ $template->id }}" name="paper_type"><option value="sheet" @selected($template->paper_type === 'sheet')>Lembar</option><option value="roll" @selected($template->paper_type === 'roll')>Roll</option></select></div>
                    <div class="field-group"><label class="field-label" for="edit-paper-width-{{ $template->id }}">Lebar kertas (mm)</label><input class="field" id="edit-paper-width-{{ $template->id }}" name="paper_width_mm" type="number" min="10" max="500" step="0.1" value="{{ $template->paper_width_mm }}" required></div>
                    <div class="field-group" data-sheet-field><label class="field-label" for="edit-paper-height-{{ $template->id }}">Tinggi kertas (mm)</label><input class="field" id="edit-paper-height-{{ $template->id }}" name="paper_height_mm" type="number" min="10" max="500" step="0.1" value="{{ $template->paper_height_mm }}"></div>
                    <div class="field-group"><label class="field-label" for="edit-label-width-{{ $template->id }}">Lebar label (mm)</label><input class="field" id="edit-label-width-{{ $template->id }}" name="label_width_mm" type="number" min="5" max="250" step="0.1" value="{{ $template->label_width_mm }}" required></div>
                    <div class="field-group"><label class="field-label" for="edit-label-height-{{ $template->id }}">Tinggi label (mm)</label><input class="field" id="edit-label-height-{{ $template->id }}" name="label_height_mm" type="number" min="5" max="250" step="0.1" value="{{ $template->label_height_mm }}" required></div>
                    <div class="field-group" data-sheet-field><label class="field-label" for="edit-columns-{{ $template->id }}">Kolom</label><input class="field" id="edit-columns-{{ $template->id }}" name="columns" type="number" min="1" max="12" value="{{ $template->columns }}" required></div>
                    <div class="field-group" data-sheet-field><label class="field-label" for="edit-rows-{{ $template->id }}">Baris</label><input class="field" id="edit-rows-{{ $template->id }}" name="rows" type="number" min="1" max="50" value="{{ $template->rows }}" required></div>
                    <div class="field-group"><label class="field-label" for="edit-h-gap-{{ $template->id }}">Jarak kolom (mm)</label><input class="field" id="edit-h-gap-{{ $template->id }}" name="gap_horizontal_mm" type="number" min="0" max="40" step="0.1" value="{{ $template->gap_horizontal_mm }}"></div>
                    <div class="field-group"><label class="field-label" for="edit-v-gap-{{ $template->id }}">Jarak baris (mm)</label><input class="field" id="edit-v-gap-{{ $template->id }}" name="gap_vertical_mm" type="number" min="0" max="40" step="0.1" value="{{ $template->gap_vertical_mm }}"></div>
                    <div class="field-group"><label class="field-label" for="edit-left-margin-{{ $template->id }}">Margin kiri/kanan (mm)</label><input class="field" id="edit-left-margin-{{ $template->id }}" name="margin_left_mm" type="number" min="0" max="100" step="0.1" value="{{ $template->margin_left_mm }}"></div>
                    <div class="field-group"><label class="field-label" for="edit-top-margin-{{ $template->id }}">Margin atas/bawah (mm)</label><input class="field" id="edit-top-margin-{{ $template->id }}" name="margin_top_mm" type="number" min="0" max="100" step="0.1" value="{{ $template->margin_top_mm }}"></div>
                </div>
                <button class="button button-small" type="submit">Simpan perubahan</button>
            </form>
            <form method="POST" action="{{ route('pos.barcode-templates.destroy', $template) }}" style="margin-top:8px" onsubmit="return confirm('Hapus template label ini?')">
                @csrf @method('DELETE')
                <button class="button button-danger button-small" type="submit">Hapus template</button>
            </form>
        </details>
    @endforeach
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Cetak label menu dan produk</h2><p>Pilih menu atau varian produk tersedia dengan barcode terdaftar. Maksimal 500 label sekali cetak.</p></div></div>
    @if ($menus->isEmpty() && $productVariants->isEmpty())
        <div class="empty-state">Belum ada item tersedia dengan barcode. Tambahkan barcode pada pengelolaan menu atau produk.</div>
    @else
        <form class="pos-order-form" method="GET" action="{{ route('pos.barcode-templates.print') }}" data-print-selection>
            <div class="field-group">
                <label class="field-label" for="print-template">Template</label>
                <select class="field" id="print-template" name="template_id" required>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div data-print-items>
                <div class="inline-form" data-print-item style="margin:10px 0">
                    <select class="field" name="items[0][item]" aria-label="Menu atau produk barcode" required>
                        <option value="">Pilih item</option>
                        @foreach ($menus as $menu)
                            <option value="menu:{{ $menu->id }}">Menu · {{ $menu->name }} · {{ $menu->barcode }}</option>
                        @endforeach
                        @foreach ($productVariants as $variant)
                            <option value="product:{{ $variant->id }}">Produk · {{ $variant->product->name }} · {{ $variant->label }} · {{ $variant->barcode }}</option>
                        @endforeach
                    </select>
                    <input class="field" name="items[0][quantity]" type="number" min="1" max="100" value="1" aria-label="Jumlah label" required>
                    <button class="button button-danger button-small" type="button" data-remove-print-item aria-label="Hapus item">Hapus</button>
                </div>
            </div>
            <button class="button button-secondary button-small" type="button" data-add-print-item>Tambah item</button>
            <button class="button" type="submit">Siapkan label</button>
        </form>
    @endif
</section>
@endif
@endsection
