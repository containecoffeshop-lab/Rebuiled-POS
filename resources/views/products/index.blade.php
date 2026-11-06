@extends('layouts.app')

@section('title', 'Produk eceran')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Inventaris terpisah</p><h1>Produk eceran</h1><p>Kelola SKU, varian, harga, barcode, dan stok produk yang tidak memakai resep.</p></div>
    <div class="nav-links"><a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a></div>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Tambah produk dan varian pertama</h2><p>Setiap produk perlu setidaknya satu varian dengan satuan dan harga sendiri.</p></div></div>
    <form method="POST" action="{{ route('pos.products.store') }}" class="form-grid">
        @csrf
        <div class="field-group"><label class="field-label" for="product-sku">SKU</label><input class="field" id="product-sku" name="sku" value="{{ old('sku') }}" maxlength="120" required></div>
        <div class="field-group"><label class="field-label" for="product-category">Kategori</label><select class="field" id="product-category" name="category_id" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="field-group"><label class="field-label" for="product-name">Nama produk</label><input class="field" id="product-name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
        <div class="field-group"><label class="field-label" for="product-description">Deskripsi (opsional)</label><input class="field" id="product-description" name="description" value="{{ old('description') }}" maxlength="500"></div>
        <div class="field-group"><label class="field-label" for="variant-label">Nama varian</label><input class="field" id="variant-label" name="label" value="{{ old('label', 'Umum') }}" maxlength="120" placeholder="Contoh: 500 ml, Merah / M" required></div>
        <div class="field-group"><label class="field-label" for="variant-unit">Satuan</label><select class="field" id="variant-unit" name="unit_id" required><option value="">Pilih satuan</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
        <div class="field-group"><label class="field-label" for="variant-barcode">Barcode (opsional)</label><input class="field" id="variant-barcode" name="barcode" value="{{ old('barcode') }}" maxlength="120"></div>
        <div class="field-group"><label class="field-label" for="variant-price">Harga jual</label><input class="field" id="variant-price" name="selling_price" type="number" min="1" max="999999999999" step="1" value="{{ old('selling_price') }}" required></div>
        <div class="field-group"><label class="field-label" for="variant-minimum">Batas minimum stok</label><input class="field" id="variant-minimum" name="minimum_quantity" type="number" min="0" max="999999999.999" step="0.001" value="{{ old('minimum_quantity', 0) }}" required></div>
        <div class="field-group"><label class="field-label" for="initial-quantity">Stok awal (opsional)</label><input class="field" id="initial-quantity" name="initial_quantity" type="number" min="0" max="999999999.999" step="0.001" value="{{ old('initial_quantity', 0) }}"></div>
        <div class="field-group"><label class="field-label" for="initial-batch">Kode batch stok awal</label><input class="field" id="initial-batch" name="batch_code" value="{{ old('batch_code') }}" maxlength="64"></div>
        <div class="field-group"><label class="field-label" for="initial-cost">HPP per satuan (opsional)</label><input class="field" id="initial-cost" name="unit_cost" type="number" min="0" max="999999999999" step="1" value="{{ old('unit_cost') }}"></div>
        <div class="field-group"><label class="field-label" for="initial-expiry">Tanggal kedaluwarsa (opsional)</label><input class="field" id="initial-expiry" name="expires_at" type="date" min="{{ today()->toDateString() }}" value="{{ old('expires_at') }}"></div>
        <div class="field-group"><button class="button" type="submit">Simpan produk</button></div>
    </form>
</section>

@forelse ($products as $product)
    <section class="panel">
        <div class="panel-head">
            <div><h2>{{ $product->name }} <span class="order-meta">· {{ $product->sku }} · {{ $product->category->name }}</span></h2>@if ($product->description)<p>{{ $product->description }}</p>@endif</div>
            <form method="POST" action="{{ route('pos.products.toggle', $product) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">{{ $product->is_available ? 'Sembunyikan produk' : 'Aktifkan produk' }}</button></form>
        </div>
        <form method="POST" action="{{ route('pos.products.update', $product) }}" class="form-grid" style="margin-bottom:20px">
            @csrf @method('PATCH')
            <div class="field-group"><label class="field-label" for="product-sku-{{ $product->id }}">SKU</label><input class="field" id="product-sku-{{ $product->id }}" name="sku" value="{{ $product->sku }}" maxlength="120" required></div>
            <div class="field-group"><label class="field-label" for="product-category-{{ $product->id }}">Kategori</label><select class="field" id="product-category-{{ $product->id }}" name="category_id" required>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($category->id === $product->category_id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="field-group"><label class="field-label" for="product-name-{{ $product->id }}">Nama produk</label><input class="field" id="product-name-{{ $product->id }}" name="name" value="{{ $product->name }}" maxlength="120" required></div>
            <div class="field-group"><label class="field-label" for="product-desc-{{ $product->id }}">Deskripsi</label><input class="field" id="product-desc-{{ $product->id }}" name="description" value="{{ $product->description }}" maxlength="500"></div>
            <div class="field-group"><button class="button button-secondary" type="submit">Simpan identitas</button></div>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Varian</th><th>Barcode</th><th>Satuan</th><th>Harga</th><th>Stok tersedia</th><th>Status</th></tr></thead><tbody>
            @foreach ($product->variants as $variant)
                <tr>
                    <td><strong>{{ $variant->label }}</strong></td>
                    <td>{{ $variant->barcode ?: '—' }}</td>
                    <td>{{ $variant->unit->name }}</td>
                    <td>Rp{{ number_format($variant->selling_price, 0, ',', '.') }}</td>
                    <td><span class="status-tag {{ $variant->is_below_minimum ? 'pending' : 'paid' }}">{{ number_format($variant->stock_on_hand, 3, ',', '.') }} layak jual</span><br><span class="order-meta">{{ number_format($variant->physical_stock, 3, ',', '.') }} {{ $variant->unit->name }} fisik{{ $variant->is_below_minimum ? ' · di bawah minimum' : '' }}</span></td>
                    <td><form method="POST" action="{{ route('pos.product-variants.toggle', $variant) }}">@csrf @method('PATCH')<button class="button button-small button-secondary" type="submit">{{ $variant->is_available ? 'Aktif' : 'Nonaktif' }}</button></form></td>
                </tr>
                <tr><td colspan="6">
                    <form method="POST" action="{{ route('pos.product-variants.update', $variant) }}" class="form-grid">@csrf @method('PATCH')
                        <div class="field-group"><label class="field-label" for="variant-label-{{ $variant->id }}">Nama varian</label><input class="field" id="variant-label-{{ $variant->id }}" name="label" value="{{ $variant->label }}" maxlength="120" required></div>
                        <div class="field-group"><label class="field-label" for="variant-unit-{{ $variant->id }}">Satuan</label><select class="field" id="variant-unit-{{ $variant->id }}" name="unit_id" required>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected($unit->id === $variant->unit_id)>{{ $unit->name }}</option>@endforeach</select></div>
                        <div class="field-group"><label class="field-label" for="variant-barcode-{{ $variant->id }}">Barcode</label><input class="field" id="variant-barcode-{{ $variant->id }}" name="barcode" value="{{ $variant->barcode }}" maxlength="120"></div>
                        <div class="field-group"><label class="field-label" for="variant-price-{{ $variant->id }}">Harga jual</label><input class="field" id="variant-price-{{ $variant->id }}" name="selling_price" type="number" min="1" max="999999999999" step="1" value="{{ $variant->selling_price }}" required></div>
                        <div class="field-group"><label class="field-label" for="variant-minimum-{{ $variant->id }}">Minimum stok</label><input class="field" id="variant-minimum-{{ $variant->id }}" name="minimum_quantity" type="number" min="0" max="999999999.999" step="0.001" value="{{ $variant->minimum_quantity }}" required></div>
                        <div class="field-group"><button class="button button-secondary" type="submit">Simpan varian</button></div>
                    </form>
                    <div class="filter-row" style="align-items:end;margin-top:12px">
                        <form method="POST" action="{{ route('pos.product-variants.stock', $variant) }}" class="form-grid" style="flex:1">@csrf
                            <div class="field-group"><label class="field-label" for="batch-{{ $variant->id }}">Batch masuk</label><input class="field" id="batch-{{ $variant->id }}" name="batch_code" maxlength="64" required></div>
                            <div class="field-group"><label class="field-label" for="quantity-{{ $variant->id }}">Jumlah masuk</label><input class="field" id="quantity-{{ $variant->id }}" name="quantity" type="number" min="0.001" max="999999999.999" step="0.001" required></div>
                            <div class="field-group"><label class="field-label" for="cost-{{ $variant->id }}">HPP per unit (opsional)</label><input class="field" id="cost-{{ $variant->id }}" name="unit_cost" type="number" min="0" max="999999999999" step="1"></div>
                            <div class="field-group"><label class="field-label" for="expiry-{{ $variant->id }}">Kedaluwarsa (opsional)</label><input class="field" id="expiry-{{ $variant->id }}" name="expires_at" type="date" min="{{ today()->toDateString() }}"></div>
                            <div class="field-group"><button class="button button-small" type="submit">Terima stok</button></div>
                        </form>
                        <form method="POST" action="{{ route('pos.product-variants.reconcile', $variant) }}" class="form-grid" style="flex:1">@csrf
                            <div class="field-group"><label class="field-label" for="count-{{ $variant->id }}">Hasil hitung fisik</label><input class="field" id="count-{{ $variant->id }}" name="counted_quantity" type="number" min="0" max="999999999.999" step="0.001" value="{{ $variant->physical_stock }}" required></div>
                            <div class="field-group"><label class="field-label" for="reference-{{ $variant->id }}">Referensi opname</label><input class="field" id="reference-{{ $variant->id }}" name="reference" maxlength="48" required></div>
                            <div class="field-group"><button class="button button-small button-secondary" type="submit">Rekonsiliasi</button></div>
                        </form>
                    </div>
                </td></tr>
            @endforeach
        </tbody></table></div>
        <form method="POST" action="{{ route('pos.products.variants.store', $product) }}" class="form-grid" style="margin-top:20px">@csrf
            <div class="field-group"><label class="field-label" for="new-label-{{ $product->id }}">Varian baru</label><input class="field" id="new-label-{{ $product->id }}" name="label" maxlength="120" required></div>
            <div class="field-group"><label class="field-label" for="new-unit-{{ $product->id }}">Satuan</label><select class="field" id="new-unit-{{ $product->id }}" name="unit_id" required>@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select></div>
            <div class="field-group"><label class="field-label" for="new-barcode-{{ $product->id }}">Barcode</label><input class="field" id="new-barcode-{{ $product->id }}" name="barcode" maxlength="120"></div>
            <div class="field-group"><label class="field-label" for="new-price-{{ $product->id }}">Harga jual</label><input class="field" id="new-price-{{ $product->id }}" name="selling_price" type="number" min="1" max="999999999999" step="1" required></div>
            <div class="field-group"><label class="field-label" for="new-minimum-{{ $product->id }}">Minimum stok</label><input class="field" id="new-minimum-{{ $product->id }}" name="minimum_quantity" type="number" min="0" max="999999999.999" step="0.001" value="0" required></div>
            <div class="field-group"><button class="button" type="submit">Tambah varian</button></div>
        </form>
    </section>
@empty
    <section class="panel empty-state">Belum ada produk eceran.</section>
@endforelse
@endsection
