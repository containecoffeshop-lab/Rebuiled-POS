@extends('layouts.app')

@section('title', 'Menu & pesan')

@section('content')
@if ($diningTable)
    <div class="alert" role="status">Memesan untuk <strong>Meja {{ $diningTable->number }}</strong>. Pesanan dari QR ini akan langsung terkait dengan meja tersebut.</div>
@endif
<section class="hero">
    <p class="eyebrow">Kopi baik, hari lebih ringan</p>
    <h1>Temukan rasa yang pas untuk hari ini.</h1>
    <p>Pilih menu favoritmu, sampaikan catatan, lalu pesanan langsung masuk ke meja kasir.</p>
    <span class="hero-note">✦ Dibuat segar setelah dipesan <span>·</span> Bayar di kasir</span>
</section>

<div class="section-heading">
    <div><h2>Menu pilihan</h2><p>Rincian harga akhir ditampilkan sebelum pesanan dikirim.</p></div>
    <span class="status-tag {{ $orderingAvailable ? '' : 'pending' }}">{{ $orderingAvailable ? 'Pemesanan tersedia' : 'Kasir sedang tutup' }}</span>
</div>
@unless ($orderingAvailable)
    <div class="alert" role="status">Pemesanan online dibuka kembali saat kasir membuka shift.</div>
@endunless
<div class="filter-row">
    <a class="filter-chip {{ ! request('category') ? 'active' : '' }}" href="{{ route('home', request()->except('category')) }}" data-preserve-cart>Semua</a>
    @foreach ($categories as $category)
        <a class="filter-chip {{ (string) request('category') === (string) $category->id ? 'active' : '' }}" href="{{ route('home', array_merge(request()->except('category'), ['category' => $category->id])) }}" data-preserve-cart>{{ $category->name }}</a>
    @endforeach
    <form class="search-form" method="GET" action="{{ route('home') }}" data-live-menu-search>
        @if (request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
        <label class="visually-hidden" for="q">Cari menu</label>
        <input class="field" id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Cari nama menu..." autocomplete="off">
    </form>
</div>

@if ($menus->isEmpty() && $productVariants->isEmpty())
    <div class="empty-state">Belum ada menu atau produk yang cocok. Coba kategori lain atau hubungi kasir.</div>
@else
    <div class="catalog-layout" data-cart-root data-cart-storage-key="customer-menu-cart" data-coupon-url="{{ route('coupons.quote') }}" data-initial-cart="{{ json_encode($initialCart, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
        @unless ($orderingAvailable)
            <p class="status-tag pending cart-open-button">Kasir belum membuka shift. Menu tetap dapat ditambahkan ke keranjang, tetapi pesanan belum dapat dikirim.</p>
        @endunless
        <button class="button button-secondary cart-open-button" type="button" data-open-cart aria-controls="customer-cart">
            Keranjang (<span data-cart-count>0</span>)
        </button>
        <section>
            @foreach ($menus->groupBy('category_id') as $categoryId => $categoryMenus)
                @php $menuCategory = $categories->firstWhere('id', (int) $categoryId); @endphp
                <section class="catalog-category" aria-label="Kategori {{ $menuCategory?->name }}">
                    <div class="section-heading category-section-heading"><div><h2>{{ $menuCategory?->name ?? 'Menu' }}</h2><p>{{ $categoryMenus->count() }} pilihan</p></div></div>
                    <div class="menu-grid">
                @foreach ($categoryMenus as $menu)
                    @php
                        $icon = str_contains(strtolower($menu->name), 'kopi') || str_contains(strtolower($menu->name), 'americano') || str_contains(strtolower($menu->name), 'latte') ? '☕' : (str_contains(strtolower($menu->name), 'roti') || str_contains(strtolower($menu->name), 'croissant') ? '🥐' : '🍵');
                        $availableServings = $inventoryAvailability->get($menu->id);
                        $menuVariantOptions = $menu->variants->where('is_active', true)->map(fn ($variant) => [
                            'id' => $variant->id,
                            'name' => $variant->name,
                            'additional_price' => $variant->additional_price,
                            'available' => $variantAvailability->get($variant->id),
                        ])->values();
                    @endphp
                    <article class="menu-card" data-menu-card data-item-type="menu" data-item-id="{{ $menu->id }}" data-item-name="{{ $menu->name }}" data-item-price="{{ $menu->selling_price }}" data-item-barcode="{{ $menu->barcode }}" data-item-variants="{{ json_encode($menuVariantOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
                        <div class="menu-art" aria-hidden="true">{{ $icon }}</div>
                        <span class="menu-category">{{ $menu->category->name }}{{ $menu->is_bundle ? ' · Paket' : '' }}</span>
                        <h3>{{ $menu->name }}</h3>
                        <p class="menu-description">{{ $menu->description ?: 'Pilihan favorit yang diracik dengan bahan terbaik.' }}</p>
                        <div class="menu-card-bottom">
                            <span class="price">Rp{{ number_format($menu->selling_price, 0, ',', '.') }}</span>
                            <button class="button button-small" type="button" data-add-menu>Tambah</button>
                        </div>
                    </article>
                @endforeach
                    </div>
                </section>
            @endforeach
            @if ($productVariants->isNotEmpty())
                <div class="section-heading" style="margin-top:30px"><div><h2>Produk eceran</h2><p>Produk memiliki varian dan stok terpisah dari bahan menu.</p></div></div>
                @foreach ($productVariants->groupBy(fn ($variant) => $variant->product->category_id) as $productCategoryId => $categoryProducts)
                    @php $productCategory = $categories->firstWhere('id', (int) $productCategoryId); @endphp
                    <section class="catalog-category" aria-label="Kategori {{ $productCategory?->name }}">
                        <div class="section-heading category-section-heading"><div><h2>{{ $productCategory?->name ?? 'Produk' }}</h2></div></div>
                        <div class="menu-grid">
                    @foreach ($categoryProducts as $variant)
                        @php $availableProductStock = $productStock->get($variant->id, 0); @endphp
                        <article class="menu-card" data-menu-card data-item-type="product" data-item-id="{{ $variant->id }}" data-item-name="{{ $variant->product->name }} · {{ $variant->label }}" data-item-price="{{ $variant->selling_price }}" data-item-barcode="{{ $variant->barcode }}">
                            <div class="menu-art" aria-hidden="true">🛍️</div>
                            <span class="menu-category">{{ $variant->product->category->name }} · {{ $variant->unit->name }}</span>
                            <h3>{{ $variant->product->name }} · {{ $variant->label }}</h3>
                            <p class="menu-description">{{ $variant->product->description ?: 'Produk pilihan dari toko.' }}</p>
                            <span class="menu-category" style="{{ $availableProductStock < 1 ? 'color:#a64e40' : 'margin-top:7px' }}">
                                {{ $availableProductStock < 1 ? 'Stok habis' : number_format($availableProductStock, 3, ',', '.').' '.$variant->unit->name.' tersedia' }}
                            </span>
                            <div class="menu-card-bottom">
                                <span class="price">Rp{{ number_format($variant->selling_price, 0, ',', '.') }}</span>
                                <button class="button button-small" type="button" data-add-menu @disabled($availableProductStock < 1)>{{ $availableProductStock < 1 ? 'Habis' : 'Tambah' }}</button>
                            </div>
                        </article>
                    @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </section>

        <dialog class="details-dialog menu-variant-dialog" data-menu-variant-dialog aria-labelledby="customer-variant-title">
            <div class="details-dialog-head">
                <h2 id="customer-variant-title" data-menu-variant-title>Pilih varian</h2>
                <button class="button button-secondary button-small" type="button" data-close-menu-variant>Tutup</button>
            </div>
            <p class="cart-hint">Pilih ukuran atau varian sebelum menambahkan ke keranjang.</p>
            <div class="menu-variant-options" data-menu-variant-options></div>
        </dialog>

        <dialog class="panel cart-panel cart-dialog" id="customer-cart" data-cart-dialog aria-labelledby="customer-cart-title">
            <div class="cart-dialog-head">
                <h2 id="customer-cart-title">Keranjang <span style="color:#839086">(<span data-cart-count>0</span>)</span></h2>
                <button class="button button-secondary button-small" type="button" data-close-cart>Tutup</button>
            </div>
            <p class="cart-hint">Menu dan produk pilihanmu akan diteruskan ke kasir.</p>
            <div class="cart-list" data-cart-list><div class="cart-empty">Keranjang masih kosong. Pilih menu untuk mulai.</div></div>
            <div class="cart-total"><span>Subtotal</span><span data-cart-subtotal>Rp0</span></div>
            <div class="cart-total" data-discount-row hidden><span>Diskon</span><span data-cart-discount>−Rp0</span></div>
            <div data-cart-charges></div>
            <div class="cart-total"><strong>Total bayar</strong><strong data-cart-total>Rp0</strong></div>
            <form method="POST" action="{{ route('orders.store') }}" data-cart-form>
                @csrf
                <div data-cart-inputs></div>
                <div class="field-group">
                    <label class="field-label" for="customer_name">Nama pemesan</label>
                    <input class="field" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="120" autocomplete="name" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="customer_phone">Nomor WhatsApp / telepon</label>
                    <input class="field" id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" maxlength="30" autocomplete="tel" data-customer-phone required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="coupon_code">Kode kupon (opsional)</label>
                    <div class="filter-row">
                        <input class="field" id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="40" autocomplete="off">
                        <button class="button button-secondary" type="button" data-apply-coupon>Periksa diskon</button>
                    </div>
                    <p class="cart-hint" data-coupon-status role="status" aria-live="polite"></p>
                </div>
                <div class="field-group">
                    @if ($diningTable)
                        <label class="field-label">Cara menikmati</label>
                        <input type="hidden" name="transaction_type" value="dine_in">
                        <div class="field" aria-label="Makan di tempat, Meja {{ $diningTable->number }}">Makan di tempat · Meja {{ $diningTable->number }}</div>
                    @else
                        <label class="field-label" for="transaction_type">Cara menikmati</label>
                        <select class="field" id="transaction_type" name="transaction_type" required>
                            <option value="takeaway" @selected(old('transaction_type') === 'takeaway')>Dibawa pulang</option>
                            <option value="dine_in" @selected(old('transaction_type') === 'dine_in')>Makan di tempat</option>
                        </select>
                    @endif
                </div>
                <div class="field-group">
                    <label class="field-label" for="notes">Catatan (opsional)</label>
                    <textarea class="field" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Contoh: gula sedikit">{{ old('notes') }}</textarea>
                </div>
                <button class="button full-width" type="submit" @disabled(! $orderingAvailable)>Tinjau pesanan</button>
                <p class="cart-hint" style="margin-top:11px">Pembayaran dilakukan langsung di kasir. Jika program poin aktif, transaksi lunas memperoleh poin; penukaran online memerlukan verifikasi OTP dan belum tersedia.</p>
            </form>
        </dialog>
        <dialog class="checkout-dialog" data-checkout-dialog aria-labelledby="customer-checkout-title">
            <div class="checkout-dialog-head">
                <div><p class="eyebrow" style="color:#65806c;margin:0 0 5px">Konfirmasi pesanan</p><h2 id="customer-checkout-title">Periksa detail pesanan</h2></div>
                <button class="button button-secondary button-small" type="button" data-close-checkout aria-label="Tutup konfirmasi">Tutup</button>
            </div>
            <div data-review-customer></div>
            <div data-review-items></div>
            <div class="cart-total"><span>Subtotal</span><span data-review-subtotal>Rp0</span></div>
            <div class="cart-total" data-review-discount-row hidden><span>Diskon</span><span data-review-discount>−Rp0</span></div>
            <div data-review-charges></div>
            <div class="cart-total"><strong>Total tagihan</strong><strong data-review-total>Rp0</strong></div>
            <p class="cart-hint">Pembayaran dilakukan di kasir saat pesanan diproses.</p>
            <div class="checkout-dialog-actions">
                <button class="button button-secondary" type="button" data-close-checkout>Periksa lagi</button>
                <button class="button" type="button" data-confirm-checkout>Kirim pesanan</button>
            </div>
        </dialog>
    </div>
@endif
@endsection
