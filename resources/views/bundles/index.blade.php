@extends('layouts.app')

@section('title', 'Paket menu')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Menu gabungan</p>
        <h1>Paket menu</h1>
        <p>Paket memakai harga tetap yang lebih rendah dari jumlah harga menu komponennya. Resep bahan tiap menu tetap dihitung saat pembayaran.</p>
    </div>
    <div class="nav-links"><a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a></div>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Buat paket</h2><p>Pilih minimal dua menu aktif dan tentukan harga paket.</p></div></div>
    <form class="pos-order-form" method="POST" action="{{ route('pos.bundles.store') }}">
        @csrf
        <div class="pos-layout" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));align-items:end">
            <div class="field-group">
                <label class="field-label" for="bundle-name">Nama paket</label>
                <input class="field" id="bundle-name" name="name" maxlength="120" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="bundle-category">Kategori</label>
                <select class="field" id="bundle-category" name="category_id" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-label" for="bundle-price">Harga paket (Rp)</label>
                <input class="field" id="bundle-price" name="selling_price" type="number" min="1" max="9999999999" step="1" required>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label" for="bundle-description">Keterangan (opsional)</label>
            <textarea class="field" id="bundle-description" name="description" rows="2" maxlength="500"></textarea>
        </div>
        <div class="field-group">
            <span class="field-label">Komponen paket</span>
            <div class="menu-grid" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));max-height:240px;overflow:auto">
                @forelse ($menus as $menu)
                    <label class="filter-chip" style="display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="component_ids[]" value="{{ $menu->id }}">
                        {{ $menu->name }} · Rp{{ number_format($menu->selling_price, 0, ',', '.') }}
                    </label>
                @empty
                    <span class="order-meta">Tambahkan menu aktif terlebih dahulu.</span>
                @endforelse
            </div>
        </div>
        <button class="button" type="submit">Simpan paket</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Paket yang tersedia</h2><p>Paket dapat disunting atau disembunyikan melalui pengelolaan menu.</p></div></div>
    @forelse ($bundles as $bundle)
        <article class="order-card">
            <div class="order-card-head">
                <div>
                    <h3>{{ $bundle->name }}</h3>
                    <p class="order-meta">{{ $bundle->category->name }} · Rp{{ number_format($bundle->selling_price, 0, ',', '.') }} · {{ $bundle->is_available ? 'Tersedia' : 'Disembunyikan' }}</p>
                    <p class="order-meta">Isi paket: {{ $bundle->bundleItems->pluck('componentMenu.name')->filter()->join(' + ') }}</p>
                </div>
                <details>
                    <summary class="nav-link" style="cursor:pointer">Ubah paket</summary>
                    <form class="pos-order-form" method="POST" action="{{ route('pos.bundles.update', $bundle) }}" style="min-width:280px;margin-top:8px">
                        @csrf @method('PATCH')
                        <div class="field-group">
                            <label class="field-label" for="bundle-name-{{ $bundle->id }}">Nama paket</label>
                            <input class="field" id="bundle-name-{{ $bundle->id }}" name="name" value="{{ $bundle->name }}" maxlength="120" required>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="bundle-category-{{ $bundle->id }}">Kategori</label>
                            <select class="field" id="bundle-category-{{ $bundle->id }}" name="category_id" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($bundle->category_id === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="bundle-price-{{ $bundle->id }}">Harga paket (Rp)</label>
                            <input class="field" id="bundle-price-{{ $bundle->id }}" name="selling_price" type="number" min="1" max="9999999999" step="1" value="{{ $bundle->selling_price }}" required>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="bundle-description-{{ $bundle->id }}">Keterangan</label>
                            <textarea class="field" id="bundle-description-{{ $bundle->id }}" name="description" rows="2" maxlength="500">{{ $bundle->description }}</textarea>
                        </div>
                        <div class="field-group">
                            <span class="field-label">Komponen</span>
                            @foreach ($menus as $menu)
                                <label class="filter-chip" style="display:flex;align-items:center;gap:8px;margin:5px 0">
                                    <input type="checkbox" name="component_ids[]" value="{{ $menu->id }}" @checked($bundle->bundleItems->contains('component_menu_id', $menu->id))>
                                    {{ $menu->name }} · Rp{{ number_format($menu->selling_price, 0, ',', '.') }}
                                </label>
                            @endforeach
                        </div>
                        <button class="button button-small" type="submit">Simpan perubahan</button>
                    </form>
                </details>
            </div>
        </article>
    @empty
        <div class="empty-state">Belum ada paket menu.</div>
    @endforelse
    <div style="margin-top:18px">{{ $bundles->links() }}</div>
</section>
@endsection
