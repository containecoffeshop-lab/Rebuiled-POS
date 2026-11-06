@extends('layouts.app')

@section('title', 'Kasir')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Ruang kerja</p>
        <h1>Halo, {{ auth()->user()->name }}.</h1>
        <p>Kelola operasional toko dari satu modul dalam satu waktu.</p>
    </div>
</div>

<div class="pos-workspace" data-pos-workspace data-default-module="{{ request('module', 'kasir') }}" data-pending-orders-url="{{ route('pos.orders.pending-count') }}" data-pending-order-count="{{ $summary['awaiting_payment'] }}">
    <aside class="pos-sidebar" aria-label="Navigasi modul">
        <button class="button button-secondary pos-sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="true" aria-label="Minimalkan navigasi"><x-icon name="chevron-back-outline" /><x-icon name="chevron-forward-outline" /><span data-sidebar-toggle-label>Minimalkan</span></button>
        <nav class="pos-sidebar-nav">
            <button type="button" class="pos-sidebar-link" data-pos-module-link="dashboard" title="Dashboard" aria-label="Dashboard"><x-icon name="home-outline" /><span>Dashboard</span></button>
            <button type="button" class="pos-sidebar-link" data-pos-module-link="ringkasan" title="Ringkasan" aria-label="Ringkasan"><x-icon name="stats-chart-outline" /><span>Ringkasan</span></button>
            <button type="button" class="pos-sidebar-link" data-pos-module-link="kasir" title="Kasir" aria-label="Kasir"><x-icon name="cash-outline" /><span>Kasir</span></button>
            <button type="button" class="pos-sidebar-link" data-pos-module-link="konsumsi" title="Konsumsi karyawan" aria-label="Konsumsi karyawan"><x-icon name="cafe-outline" /><span>Konsumsi karyawan</span></button>
            <button type="button" class="pos-sidebar-link" data-pos-module-link="pesanan" title="Pesanan" aria-label="Pesanan"><x-icon name="receipt-outline" /><span>Pesanan</span></button>
            @if (auth()->user()->role === 'manager')
                <button type="button" class="pos-sidebar-link" data-pos-module-link="menu" title="Menu" aria-label="Menu dan HPP"><x-icon name="restaurant-outline" /><span>Menu & HPP</span></button>
                <button type="button" class="pos-sidebar-link" data-pos-module-link="stok" title="Stok" aria-label="Stok bahan"><x-icon name="cube-outline" /><span>Stok bahan</span></button>
                <button type="button" class="pos-sidebar-link" data-pos-module-link="meja" title="Meja" aria-label="Meja dan QR"><x-icon name="qr-code-outline" /><span>Meja & QR</span></button>
                <button type="button" class="pos-sidebar-link" data-pos-module-link="kupon" title="Kupon" aria-label="Kupon"><x-icon name="pricetag-outline" /><span>Kupon</span></button>
                <button type="button" class="pos-sidebar-link" data-pos-module-link="pelanggan" title="Pelanggan" aria-label="Pelanggan"><x-icon name="people-outline" /><span>Pelanggan</span></button>
                <a class="pos-sidebar-link" href="{{ route('pos.returns.index') }}" title="Retur" aria-label="Retur penjualan"><x-icon name="return-up-back-outline" /><span>Retur</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.products.index') }}" title="Produk eceran" aria-label="Produk eceran"><x-icon name="bag-handle-outline" /><span>Produk eceran</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.purchases.index') }}" title="Pembelian" aria-label="Pembelian"><x-icon name="cart-outline" /><span>Pembelian</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.expenses.index') }}" title="Pengeluaran" aria-label="Pengeluaran"><x-icon name="wallet-outline" /><span>Pengeluaran</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.reports.index') }}" title="Laporan" aria-label="Laporan"><x-icon name="bar-chart-outline" /><span>Laporan</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.promotions.index') }}" title="Promo menu" aria-label="Promo menu"><x-icon name="sparkles-outline" /><span>Promo menu</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.bundles.index') }}" title="Paket menu" aria-label="Paket menu"><x-icon name="layers-outline" /><span>Paket menu</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.settings.index') }}" title="Pengaturan" aria-label="Pengaturan toko"><x-icon name="settings-outline" /><span>Pengaturan toko</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.stock-takes.index') }}" title="Stok opname" aria-label="Stok opname"><x-icon name="clipboard-outline" /><span>Stok opname</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.barcode-templates.index') }}" title="Cetak barcode" aria-label="Cetak barcode"><x-icon name="barcode-outline" /><span>Cetak barcode</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.users.index') }}" title="Pengguna" aria-label="Pengguna"><x-icon name="person-outline" /><span>Pengguna</span></a>
                <a class="pos-sidebar-link" href="{{ route('pos.audit.index') }}" title="Audit" aria-label="Audit aktivitas"><x-icon name="time-outline" /><span>Audit</span></a>
            @endif
        </nav>
    </aside>
    <div class="pos-module-content">
<div class="pos-toolbar no-print">
    <div class="pos-toolbar-title">
        <strong>{{ $storeSettings['name'] ?? 'Kasir' }}</strong>
        <span>{{ auth()->user()->name }}</span>
    </div>
    <div class="pos-toolbar-actions">
        <button class="button button-secondary" type="button" data-dashboard-toggle>Dashboard</button>
        <button class="button" type="button" data-pos-module-link="kasir" aria-label="Buka kasir">Buka kasir</button>
        <button class="button button-secondary kitchen-shortcut" type="button" data-pos-module-link="pesanan" aria-label="Pesanan masuk" title="Pesanan masuk">
            <x-icon name="receipt-outline" />
            <span>Pesanan</span><span class="kitchen-badge" data-pending-order-badge @if ($summary['awaiting_payment'] < 1) hidden @endif>{{ $summary['awaiting_payment'] }}</span>
        </button>
        <button class="button button-secondary printer-status-button" type="button" data-printer-dialog-open aria-haspopup="dialog" aria-controls="printer-dialog" aria-label="Status printer">
            <x-icon name="bluetooth-outline" class="bluetooth-icon" />
            <span class="printer-status-dot" data-printer-status-dot></span>
        </button>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-secondary" type="submit">Keluar</button></form>
    </div>
</div>
<div class="pos-module" data-pos-module="ringkasan">
    <form method="GET" action="{{ route('pos.index') }}" class="filter-row" style="justify-content:end;margin:12px 0">
        <input type="hidden" name="module" value="ringkasan">
        <label class="field-label" for="summary-month" style="margin:0">Bulan ringkasan</label>
        <input class="field" id="summary-month" name="month" type="month" value="{{ $monthlySummary['month'] }}" style="width:auto">
        <button class="button button-secondary button-small" type="submit">Terapkan</button>
        @if ($monthlySummary['expenses'] !== null)
            <a class="button button-secondary button-small" href="{{ route('pos.summary.export', ['month' => $monthlySummary['month']]) }}">Ekspor Excel</a>
            <a class="button button-secondary button-small" href="{{ route('pos.summary.pdf', ['month' => $monthlySummary['month']]) }}" target="_blank" rel="noopener">Cetak / Simpan PDF</a>
        @endif
    </form>
<div class="panel-head"><div><h2>Ringkasan dan statistik</h2><p>Rekap penjualan, target, tren harian, dan performa menu untuk bulan terpilih.</p></div></div>
<div class="stats-grid">
    <article class="stat-card"><span class="stat-label">Penjualan hari ini</span><strong class="stat-value">Rp{{ number_format($summary['sales_today'], 0, ',', '.') }}</strong></article>
    <article class="stat-card"><span class="stat-label">Transaksi hari ini</span><strong class="stat-value">{{ number_format($summary['orders_today'], 0, ',', '.') }}</strong></article>
    <article class="stat-card"><span class="stat-label">Menunggu pembayaran</span><strong class="stat-value">{{ number_format($summary['awaiting_payment'], 0, ',', '.') }}</strong></article>
    <article class="stat-card"><span class="stat-label">Dalam alur dapur</span><strong class="stat-value">{{ number_format($summary['in_kitchen'], 0, ',', '.') }}</strong></article>
    <article class="stat-card"><span class="stat-label">Pemasukan bulan ini</span><strong class="stat-value">Rp{{ number_format($monthlySummary['income'], 0, ',', '.') }}</strong><small class="cart-hint">Dari transaksi lunas bulan berjalan</small></article>
    @if ($monthlySummary['expenses'] !== null)
        <article class="stat-card"><span class="stat-label">Pengeluaran dibayar bulan ini</span><strong class="stat-value">Rp{{ number_format($monthlySummary['expenses'], 0, ',', '.') }}</strong><small class="cart-hint">Berdasarkan pembayaran pengeluaran</small></article>
        <article class="stat-card"><span class="stat-label">Total biaya operasional bulan ini</span><strong class="stat-value">Rp{{ number_format($monthlySummary['operational_cost'], 0, ',', '.') }}</strong><small class="cart-hint">Termasuk tagihan yang belum lunas</small></article>
        <article class="stat-card"><span class="stat-label">HPP bahan penjualan</span><strong class="stat-value">{{ $monthlySummary['ingredient_cost'] === null ? 'Belum lengkap' : 'Rp'.number_format($monthlySummary['ingredient_cost'], 0, ',', '.') }}</strong><small class="cart-hint">Bahan yang terpakai untuk transaksi penjualan</small></article>
        <article class="stat-card"><span class="stat-label">HPP konsumsi karyawan</span><strong class="stat-value">Rp{{ number_format($monthlySummary['employee_consumption_cost'], 0, ',', '.') }}</strong><small class="cart-hint">Dicatat terpisah sebagai pengeluaran non-tunai kategori Konsumsi</small></article>
        <article class="stat-card"><span class="stat-label">Target omzet per hari</span><strong class="stat-value">{{ $monthlySummary['target_turnover'] === null ? 'Belum dapat dihitung' : 'Rp'.number_format($monthlySummary['target_turnover'], 0, ',', '.') }}</strong><small class="cart-hint">({{ $monthlySummary['ingredient_cost'] === null ? 'HPP belum lengkap' : 'HPP bahan penjualan + seluruh biaya operasional' }} + 5%) ÷ {{ $monthlySummary['days_in_month'] }} hari kalender</small></article>
    @endif
</div>

<section class="panel" style="margin-top:20px">
    <div class="panel-head"><div><h2>Statistik penjualan dan pengeluaran</h2><p>Perbandingan pemasukan transaksi lunas, pembayaran pengeluaran, dan target omzet harian.</p></div></div>
    <div class="summary-chart-legend">
        <span><i class="summary-legend-dot income"></i>Pemasukan</span>
        @if ($monthlySummary['expenses'] !== null)<span><i class="summary-legend-dot expense"></i>Pengeluaran dibayar</span>@endif
        <span><i class="summary-legend-dot target"></i>Target harian</span>
    </div>
    <div class="summary-chart-wrap">
        <svg class="summary-chart" viewBox="0 0 900 280" role="img" aria-label="Grafik statistik bulanan" data-summary-chart data-points="{{ json_encode($dailySummary, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}" data-show-expenses="{{ $monthlySummary['expenses'] !== null ? 'true' : 'false' }}"></svg>
    </div>
</section>

<div class="pos-layout summary-menu-grid" style="margin-top:20px">
    <section class="panel">
        <div class="panel-head"><div><h2>10 menu terlaris</h2><p>Diurutkan dari jumlah item terjual pada bulan terpilih.</p></div></div>
        <div class="table-wrap"><table class="menu-table">
            <thead><tr><th>Menu</th><th>Terjual</th><th>Nilai item</th></tr></thead>
            <tbody>
                @forelse ($topMenus as $menu)
                    <tr><td>{{ $menu['name'] }}</td><td>{{ number_format($menu['quantity'], 0, ',', '.') }}</td><td>Rp{{ number_format($menu['item_sales'], 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="3">Belum ada penjualan lunas pada bulan ini.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>10 menu paling sedikit terjual</h2><p>Termasuk menu aktif yang belum terjual pada bulan terpilih.</p></div></div>
        <div class="table-wrap"><table class="menu-table">
            <thead><tr><th>Menu</th><th>Terjual</th><th>Nilai item</th></tr></thead>
            <tbody>
                @forelse ($leastMenus as $menu)
                    <tr><td>{{ $menu['name'] }}</td><td>{{ number_format($menu['quantity'], 0, ',', '.') }}</td><td>Rp{{ number_format($menu['item_sales'], 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="3">Belum ada menu untuk diringkas.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>

</div>

<section class="panel" id="buat-transaksi" data-pos-module="kasir">
    <section class="panel shift-panel">
        <div class="shift-indicator">
            <span class="status-dot {{ $activeShift ? '' : 'off' }}"></span>
            <div>
                <strong>{{ $activeShift ? 'Shift kasir sedang berjalan' : 'Shift kasir belum dibuka' }}</strong>
                <p class="order-meta" style="margin-top:5px">
                    {{ $activeShift ? 'Dimulai '.$activeShift->opened_at->locale('id')->translatedFormat('d M Y, H:i').' · Uang awal Rp'.number_format($activeShift->starting_cash, 0, ',', '.') : 'Buka shift untuk menerima transaksi dan pesanan online.' }}
                </p>
            </div>
        </div>
        @if ($activeShift)
            <form class="inline-form" method="POST" action="{{ route('pos.shift.close') }}">
                @csrf
                <div>
                    <label class="field-label" for="ending_cash">Uang kas akhir</label>
                    <input class="field" id="ending_cash" name="ending_cash" type="number" min="0" max="999999999999" step="1" placeholder="Hitung kas fisik" required>
                </div>
                <button class="button button-danger" type="submit">Tutup shift</button>
            </form>
        @else
            <form class="inline-form" method="POST" action="{{ route('pos.shift.open') }}">
                @csrf
                <div>
                    <label class="field-label" for="starting_cash">Uang kas awal</label>
                    <input class="field" id="starting_cash" name="starting_cash" type="number" min="0" max="999999999999" step="1" value="{{ old('starting_cash', 0) }}" required>
                </div>
                <button class="button" type="submit">Buka shift & kasir</button>
            </form>
        @endif
    </section>
    <div class="panel-head">
        <div><h2>Buat transaksi</h2><p>Pilih menu atau produk eceran, atur jumlah, lalu catat pembayaran.</p></div>
        @unless ($activeShift)
            <span class="status-tag pending">Buka shift untuk mulai</span>
        @endunless
    </div>
    @if ($availableMenus->isEmpty() && $productVariants->isEmpty())
        <div class="empty-state">Belum ada menu atau produk aktif. Tambahkan menu atau produk eceran terlebih dahulu.</div>
    @else
        <div class="pos-layout" data-cart-root data-cart-storage-key="pos-menu-cart" data-shift-active="{{ $activeShift ? 'true' : 'false' }}" data-coupon-url="{{ route('pos.loyalty.quote') }}" data-initial-cart="{{ json_encode($initialCart, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
            <form class="inline-form catalog-search-form" method="GET" action="{{ route('pos.index') }}" data-live-menu-search style="grid-column:1 / -1">
                <input type="hidden" name="module" value="kasir">
                <label class="visually-hidden" for="pos-menu-search">Cari nama menu</label>
                <input class="field" id="pos-menu-search" type="search" name="menu_q" value="{{ $catalogFilters['menu_q'] }}" placeholder="Cari nama menu..." autocomplete="off">
                <label class="visually-hidden" for="pos-menu-category">Kategori</label>
                <select class="field" id="pos-menu-category" name="menu_category" data-live-search-change>
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $catalogFilters['menu_category'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <span class="cart-hint">Pencarian otomatis berdasarkan nama menu dan kategori.</span>
            </form>
            <div class="pos-catalog">
                @foreach ($categories as $category)
                    @php $categoryMenus = $availableMenus->where('category_id', $category->id); @endphp
                    @if ($categoryMenus->isNotEmpty())
                    <section class="pos-catalog-group">
                        <h3>{{ $category->name }}</h3>
                        <div class="pos-catalog-group-grid">
                @foreach ($categoryMenus as $menu)
                    @php
                        $availableServings = $inventoryAvailability->get($menu->id);
                        $menuVariantOptions = $menu->variants->where('is_active', true)->map(fn ($variant) => [
                            'id' => $variant->id,
                            'name' => $variant->name,
                            'additional_price' => $variant->additional_price,
                            'hpp' => $variant->estimated_hpp,
                            'available' => $variantAvailability->get($variant->id),
                        ])->values();
                    @endphp
                    <article class="pos-item" data-menu-card data-item-type="menu" data-item-id="{{ $menu->id }}" data-item-name="{{ $menu->name }}" data-item-price="{{ $menu->selling_price }}" data-item-barcode="{{ $menu->barcode }}" data-menu-barcode="{{ $menu->barcode }}" data-item-variants="{{ json_encode($menuVariantOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
                        @if ($menu->image_path)<img class="pos-item-image" src="{{ asset($menu->image_path) }}" alt="" loading="lazy">@endif
                        <div>
                            <h3>{{ $menu->name }}</h3>
                            <p>{{ $menu->category->name }} · Rp{{ number_format($menu->selling_price, 0, ',', '.') }}</p>
                        </div>
                        <button class="button button-small" type="button" data-add-menu @disabled(! $activeShift)>Tambah</button>
                    </article>
                @endforeach
                        </div>
                    </section>
                    @endif
                @endforeach
                @if ($productVariants->isNotEmpty())
                    <section class="pos-catalog-group">
                        <h3>Produk eceran</h3>
                        @foreach ($productVariants->groupBy(fn ($variant) => $variant->product->category_id) as $productCategoryId => $categoryProducts)
                            @php $productCategory = $categories->firstWhere('id', (int) $productCategoryId); @endphp
                            <h4>{{ $productCategory?->name ?? 'Produk' }}</h4>
                            <div class="pos-catalog-group-grid">
                @foreach ($categoryProducts as $variant)
                    @php $availableProductStock = $productStock->get($variant->id, 0); @endphp
                    <article class="pos-item" data-menu-card data-item-type="product" data-item-id="{{ $variant->id }}" data-item-name="{{ $variant->product->name }} · {{ $variant->label }}" data-item-price="{{ $variant->selling_price }}" data-item-barcode="{{ $variant->barcode }}">
                        <div>
                            <h3>{{ $variant->product->name }} · {{ $variant->label }}</h3>
                            <p>{{ $variant->product->category->name }} · {{ $variant->unit->name }} · Rp{{ number_format($variant->selling_price, 0, ',', '.') }}</p>
                            <p>{{ $availableProductStock > 0 ? number_format($availableProductStock, 3, ',', '.').' '.$variant->unit->name.' tersedia' : 'Stok produk habis' }}</p>
                        </div>
                        <button class="button button-small" type="button" data-add-menu @disabled(! $activeShift || $availableProductStock < 1)>{{ $availableProductStock < 1 ? 'Habis' : 'Tambah' }}</button>
                    </article>
                @endforeach
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>
            <aside class="pos-order-form pos-cart-panel">
                <h3 style="margin:0 0 4px;font-size:16px">Transaksi baru</h3>
                <p class="cart-hint">Pilih produk. Rincian transaksi berisi nama, harga, jumlah, dan total.</p>
                <div class="cart-list" data-cart-list></div>
                <div class="cart-total"><span>Subtotal</span><span data-cart-subtotal>Rp0</span></div>
                <div class="cart-total" data-discount-row hidden><span>Diskon</span><span data-cart-discount>−Rp0</span></div>
                <div data-cart-charges></div>
                <div class="cart-total"><strong>Total bayar</strong><strong data-cart-total>Rp0</strong></div>
                <button class="button full-width" type="button" data-open-checkout @disabled(! $activeShift)>Proses transaksi</button>
            </aside>
            <dialog class="checkout-dialog" data-checkout-dialog aria-labelledby="pos-checkout-title">
                <div class="checkout-dialog-head">
                    <div><p class="eyebrow" style="color:#65806c;margin:0 0 5px">Pembayaran</p><h2 id="pos-checkout-title">Proses transaksi</h2></div>
                    <button class="button button-secondary button-small" type="button" data-close-checkout aria-label="Tutup konfirmasi">Tutup</button>
                </div>
                <div data-review-items></div>
                <div class="cart-total"><span>Subtotal</span><span data-review-subtotal>Rp0</span></div>
                <div class="cart-total" data-review-discount-row hidden><span>Diskon</span><span data-review-discount>−Rp0</span></div>
                <div data-review-charges></div>
                <div class="cart-total"><strong>Total bayar</strong><strong data-review-total>Rp0</strong></div>
                <form method="POST" action="{{ route('pos.orders.store') }}" data-cart-form id="pos-order-form">
                    @csrf
                    <div data-cart-inputs></div>
                    <div class="field-group">
                        <span class="field-label">Metode pembayaran</span>
                        <div class="payment-method-buttons" role="group" aria-label="Metode pembayaran">
                            <button class="button button-secondary" type="button" data-payment-choice="cash" aria-pressed="{{ old('payment_method', 'cash') === 'cash' ? 'true' : 'false' }}">Tunai</button>
                            <button class="button button-secondary" type="button" data-payment-choice="transfer" aria-pressed="{{ old('payment_method') === 'transfer' ? 'true' : 'false' }}">Transfer</button>
                        </div>
                        <select class="visually-hidden" id="pos_payment_method" name="payment_method" data-payment-method required aria-hidden="true" tabindex="-1">
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Tunai</option>
                            <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="pos_amount_paid">Nominal diterima</label>
                        <div class="cash-shortcuts" aria-label="Pilihan nominal pembayaran">
                            <button class="button button-secondary button-small" type="button" data-cash-tender="exact">Uang pas</button>
                            <button class="button button-secondary button-small" type="button" data-cash-tender="20000">Rp20.000</button>
                            <button class="button button-secondary button-small" type="button" data-cash-tender="50000">Rp50.000</button>
                            <button class="button button-secondary button-small" type="button" data-cash-tender="100000">Rp100.000</button>
                        </div>
                        <input class="field" id="pos_amount_paid" name="amount_paid" data-amount-paid type="number" min="0" max="999999999999" step="1" value="{{ old('amount_paid', 0) }}" placeholder="Masukkan nominal diterima" required>
                        <div class="change-summary" aria-live="polite"><span data-change-label>Kembalian</span><strong data-change-amount>Rp0</strong></div>
                    </div>
                    <div class="checkout-fields-grid">
                        <div class="field-group">
                            <label class="field-label" for="pos_customer_name">Nama pelanggan (opsional)</label>
                            <input class="field" id="pos_customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="120" placeholder="Pelanggan kasir">
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="pos_customer_phone">Nomor telepon (opsional)</label>
                            <input class="field" id="pos_customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" maxlength="30" autocomplete="tel" data-customer-phone>
                        </div>
                    </div>
                    <div class="checkout-fields-grid">
                        <div class="field-group">
                            <label class="field-label" for="pos_transaction_type">Jenis pesanan</label>
                            <select class="field" id="pos_transaction_type" name="transaction_type" required>
                                <option value="takeaway" @selected(old('transaction_type', 'takeaway') === 'takeaway')>Dibawa pulang</option>
                                <option value="dine_in" @selected(old('transaction_type') === 'dine_in')>Makan di tempat</option>
                            </select>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="pos_dining_table_id">Meja (opsional)</label>
                            <select class="field" id="pos_dining_table_id" name="dining_table_id">
                                <option value="">Tanpa meja</option>
                                @foreach ($diningTables as $table)
                                    <option value="{{ $table->id }}" @selected((string) old('dining_table_id') === (string) $table->id)>{{ $table->number }} · {{ $table->status === 'occupied' ? 'Terisi' : 'Tersedia' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="pos_coupon_code">Kode kupon (opsional)</label>
                        <div class="filter-row">
                            <input class="field" id="pos_coupon_code" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="40" autocomplete="off">
                            <button class="button button-secondary" type="button" data-apply-coupon>Periksa diskon</button>
                        </div>
                        <p class="cart-hint" data-coupon-status role="status" aria-live="polite"></p>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="pos_redeem_points">Tukar poin loyalitas</label>
                        <input class="field" id="pos_redeem_points" name="redeem_points" type="number" min="0" max="999999999999" step="1" value="{{ old('redeem_points', 0) }}" data-redeem-points @disabled($loyaltyPointValue < 1)>
                        <p class="cart-hint" data-loyalty-status role="status" aria-live="polite">
                            @if ($loyaltyPointValue > 0)
                                Masukkan nomor telepon pelanggan untuk memeriksa saldo. Nilai 1 poin: Rp{{ number_format($loyaltyPointValue, 0, ',', '.') }}.
                            @else
                                Penukaran poin belum diaktifkan pengelola.
                            @endif
                        </p>
                    </div>
                    <div class="field-group" data-bank-selector hidden>
                        <label class="field-label" for="pos_bank_account_id">Rekening tujuan</label>
                        <select class="field" id="pos_bank_account_id" name="bank_account_id">
                            <option value="">Tidak dicatat</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->bank_name }} · {{ $account->account_name }} · {{ $account->account_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="pos_notes">Catatan (opsional)</label>
                        <input class="field" id="pos_notes" name="notes" value="{{ old('notes') }}" maxlength="1000">
                    </div>
                    <div class="checkout-dialog-actions">
                        <button class="button button-secondary" type="button" data-close-checkout>Kembali</button>
                        <button class="button" type="submit" @disabled(! $activeShift)>Selesaikan transaksi</button>
                    </div>
                </form>
            </dialog>
            <dialog class="details-dialog menu-variant-dialog" data-menu-variant-dialog aria-labelledby="pos-variant-title">
                <div class="details-dialog-head">
                    <h2 id="pos-variant-title" data-menu-variant-title>Pilih varian</h2>
                    <button class="button button-secondary button-small" type="button" data-close-menu-variant>Tutup</button>
                </div>
                <p class="cart-hint">Pilih ukuran atau varian sebelum menambahkan ke transaksi.</p>
                <div class="menu-variant-options" data-menu-variant-options></div>
            </dialog>
        </div>
    @endif
</section>

<section class="panel" id="konsumsi-karyawan" data-pos-module="konsumsi" data-employee-consumption>
    <div class="panel-head">
        <div><h2>Konsumsi karyawan</h2><p>Catat minuman tanpa pembayaran. Stok bahan dipotong dan HPP dicatat sebagai biaya konsumsi.</p></div>
        @unless ($activeShift)
            <span class="status-tag pending">Buka shift untuk mulai</span>
        @endunless
    </div>
    @if (! $activeShift)
        <div class="empty-state">Konsumsi karyawan dapat dicatat setelah shift kasir dibuka.</div>
    @elseif ($employees->isEmpty())
        <div class="empty-state">Belum ada akun karyawan kasir yang aktif. Aktifkan atau tambahkan akun melalui modul Pengguna.</div>
    @elseif ($availableMenus->isEmpty())
        <div class="empty-state">Belum ada menu yang dapat dipilih.</div>
    @else
        <form method="POST" action="{{ route('pos.employee-consumptions.store') }}" data-employee-consumption-form>
            @csrf
            <div class="checkout-fields-grid">
                <div class="field-group">
                    <label class="field-label" for="employee-consumption-recipient">Karyawan penerima</label>
                    <select class="field" id="employee-consumption-recipient" name="employee_id" required>
                        <option value="">Pilih karyawan</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label">Estimasi HPP konsumsi</label>
                    <div class="field employee-consumption-total" data-employee-total>Rp0</div>
                </div>
            </div>
            <div class="pos-layout employee-consumption-layout">
                <div class="pos-catalog">
                    @foreach ($availableMenus->where('is_bundle', false) as $menu)
                        @php
                            $availableServings = $inventoryAvailability->get($menu->id);
                            $menuVariantOptions = $menu->variants->where('is_active', true)->map(fn ($variant) => [
                                'id' => $variant->id,
                                'name' => $variant->name,
                                'additional_price' => $variant->additional_price,
                                'hpp' => $variant->estimated_hpp,
                                'available' => $variantAvailability->get($variant->id),
                            ])->values();
                        @endphp
                        <article class="pos-item" data-menu-card data-item-type="menu" data-item-id="{{ $menu->id }}" data-item-name="{{ $menu->name }}" data-item-price="{{ $menu->selling_price }}" data-item-cost="{{ $menuCostEstimates->get($menu->id)['cost'] ?? '' }}" data-item-variants="{{ json_encode($menuVariantOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}">
                            @if ($menu->image_path)<img class="pos-item-image" src="{{ asset($menu->image_path) }}" alt="" loading="lazy">@endif
                            <div>
                                <h3>{{ $menu->name }}</h3>
                                <p>Estimasi HPP {{ $menuCostEstimates->get($menu->id)['cost'] === null ? 'belum diketahui' : 'Rp'.number_format($menuCostEstimates->get($menu->id)['cost'], 0, ',', '.') }}</p>
                                @if ($availableServings !== null)<p>{{ $availableServings }} porsi tersedia</p>@endif
                            </div>
                            <button class="button button-small" type="button" data-employee-add @disabled($availableServings === 0 || $menuCostEstimates->get($menu->id)['cost'] === null)>Tambah</button>
                            @if ($menuCostEstimates->get($menu->id)['cost'] === null)
                                <p class="cart-hint">Lengkapi HPP batch untuk mencatat konsumsi.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="pos-order-form">
                    <h3 style="margin-top:0">Daftar konsumsi</h3>
                    <div class="cart-list" data-employee-cart><p class="cart-empty">Pilih menu untuk mulai.</p></div>
                    <div data-employee-inputs></div>
                    <button class="button full-width" type="submit" data-record-employee-consumption disabled>Catat konsumsi</button>
                </div>
            </div>
        </form>
        <dialog class="details-dialog menu-variant-dialog" data-menu-variant-dialog aria-labelledby="employee-variant-title">
            <div class="details-dialog-head">
                <h2 id="employee-variant-title" data-menu-variant-title>Pilih varian</h2>
                <button class="button button-secondary button-small" type="button" data-close-menu-variant>Tutup</button>
            </div>
            <div class="menu-variant-options" data-menu-variant-options></div>
        </dialog>
    @endif
    <div class="panel" style="margin-top:20px">
        <div class="panel-head"><div><h3>Riwayat konsumsi karyawan</h3><p>Catatan tanpa pembayaran, beserta HPP bahan yang dipakai.</p></div></div>
        @if ($employeeConsumptions->isEmpty())
            <div class="empty-state">Belum ada konsumsi karyawan yang dicatat.</div>
        @else
            @foreach ($employeeConsumptions as $consumption)
                <article class="order-card">
                    <div class="order-card-head">
                        <div>
                            <h4 class="order-code">{{ $consumption->invoice }}</h4>
                            <p class="order-meta">{{ $consumption->employee?->name ?? $consumption->customer_name }} · {{ $consumption->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <span class="status-tag">HPP Rp{{ number_format($consumption->cogs_total ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <ul class="order-lines">
                        @foreach ($consumption->details as $detail)
                            <li>{{ $detail->quantity }} × {{ $detail->item_name }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        @endif
    </div>
</section>

<section class="panel" id="pesanan" data-pos-module="pesanan">
    <div class="panel-head">
        <div><h2>Pesanan & transaksi</h2><p>Pemesanan pelanggan dan kasir tercatat bersama.</p></div>
        <span class="status-tag">{{ $transactions->total() }} transaksi</span>
    </div>
    <form method="GET" action="{{ route('pos.index') }}" class="filter-row" style="align-items:end">
        <input type="hidden" name="module" value="pesanan">
        <div style="flex:1 1 190px">
            <label class="field-label" for="transaction-search">Cari transaksi</label>
            <input class="field" id="transaction-search" name="search" value="{{ $filters['search'] }}" placeholder="Invoice, pelanggan, telepon, menu">
        </div>
        <div>
            <label class="field-label" for="transaction-status">Pembayaran</label>
            <select class="field" id="transaction-status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua status</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Belum dibayar</option>
                <option value="paid" @selected($filters['status'] === 'paid')>Lunas</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Dibatalkan</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="kitchen-status">Dapur</label>
            <select class="field" id="kitchen-status" name="kitchen_status">
                <option value="all" @selected($filters['kitchen_status'] === 'all')>Semua status</option>
                <option value="pending" @selected($filters['kitchen_status'] === 'pending')>Antre dapur</option>
                <option value="onprogress" @selected($filters['kitchen_status'] === 'onprogress')>Sedang dibuat</option>
                <option value="success" @selected($filters['kitchen_status'] === 'success')>Selesai</option>
                <option value="cancelled" @selected($filters['kitchen_status'] === 'cancelled')>Dibatalkan</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="date-from">Dari tanggal</label>
            <input class="field" id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] }}">
        </div>
        <div>
            <label class="field-label" for="date-to">Sampai tanggal</label>
            <input class="field" id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] }}">
        </div>
        <button class="button" type="submit">Terapkan</button>
        <a class="button button-secondary" href="{{ route('pos.index') }}">Reset</a>
    </form>
    @if ($transactions->isEmpty())
        <div class="empty-state">{{ collect($filters)->contains(fn ($value) => $value !== '' && $value !== 'all') ? 'Tidak ada transaksi yang sesuai filter.' : 'Belum ada transaksi. Pesanan baru akan muncul di sini.' }}</div>
    @else
        @foreach ($transactions as $transaction)
            @php
                $paymentLabel = ['pending' => 'Belum dibayar', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'][$transaction->status] ?? $transaction->status;
                $hasKitchenItems = $transaction->details->contains(fn ($detail) => $detail->item_type === 'menu');
                $kitchenLabel = $hasKitchenItems
                    ? (['pending' => 'Antre dapur', 'onprogress' => 'Sedang dibuat', 'success' => 'Selesai', 'cancelled' => 'Dibatalkan'][$transaction->kitchen_status] ?? $transaction->kitchen_status)
                    : 'Tidak perlu dapur';
            @endphp
            <article class="order-card">
                <div class="order-card-head">
                    <div>
                        <h3 class="order-code">{{ $transaction->invoice }}</h3>
                        <p class="order-meta">{{ $transaction->customer_name }}{{ $transaction->customer_phone ? ' · '.$transaction->customer_phone : '' }} · {{ $transaction->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</p>
                        <p class="order-meta">{{ $transaction->transaction_type === 'dine_in' ? 'Makan di tempat' : 'Dibawa pulang' }}{{ $transaction->diningTable ? ' · Meja '.$transaction->diningTable->number : '' }} · Total Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}{{ $transaction->discount ? ' · diskon Rp'.number_format($transaction->discount, 0, ',', '.') : '' }}{{ $transaction->bankAccount ? ' · Transfer '.$transaction->bankAccount->bank_name : '' }}</p>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;justify-content:end;gap:6px">
                        <span class="status-tag {{ $transaction->status }}">{{ $paymentLabel }}</span>
                        <span class="status-tag {{ $hasKitchenItems ? $transaction->kitchen_status : 'paid' }}">{{ $kitchenLabel }}</span>
                    </div>
                </div>
                <ul class="order-lines">
                    @foreach ($transaction->details as $detail)
                        <li>{{ $detail->quantity }} × {{ $detail->item_name }} — Rp{{ number_format($detail->line_total, 0, ',', '.') }}@if ($detail->promotion_discount > 0) <span class="order-meta">(promo {{ $detail->promotion_name }}: −Rp{{ number_format($detail->promotion_discount, 0, ',', '.') }})</span>@endif</li>
                    @endforeach
                    @if ($transaction->notes)<li>Catatan: {{ $transaction->notes }}</li>@endif
                    @if ($transaction->loyalty_points_redeemed > 0)<li>Tukar {{ number_format($transaction->loyalty_points_redeemed, 0, ',', '.') }} poin · diskon Rp{{ number_format($transaction->loyalty_discount, 0, ',', '.') }}</li>@endif
                    @if ($transaction->loyalty_points_earned > 0)<li>Poin loyalitas diperoleh: {{ number_format($transaction->loyalty_points_earned, 0, ',', '.') }}</li>@endif
                </ul>
                @foreach ($transaction->charges as $charge)
                    <p class="order-meta">{{ $charge->name }} ({{ $charge->code }}) · {{ $charge->rate }}: Rp{{ number_format($charge->amount, 0, ',', '.') }}</p>
                @endforeach
                <div class="order-actions">
                    @if ($transaction->status === 'pending')
                        <form class="inline-form" method="POST" action="{{ route('pos.orders.paid', $transaction) }}">
                            @csrf @method('PATCH')
                            <label class="visually-hidden" for="method-{{ $transaction->id }}">Metode pembayaran</label>
                            <select class="field" id="method-{{ $transaction->id }}" name="payment_method" data-payment-method required>
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                            <select class="field" name="bank_account_id" data-bank-selector hidden aria-label="Rekening tujuan">
                                <option value="">Tidak dicatat</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_name }} · {{ $account->account_number }}</option>
                                @endforeach
                            </select>
                            <label class="visually-hidden" for="paid-{{ $transaction->id }}">Uang diterima</label>
                            <input class="field" id="paid-{{ $transaction->id }}" name="amount_paid" type="number" min="{{ $transaction->grand_total }}" max="999999999999" value="{{ $transaction->grand_total }}" required>
                            <button class="button button-small" type="submit">Catat pembayaran</button>
                        </form>
                    @endif
                    @if ($hasKitchenItems && $transaction->status !== 'cancelled' && $transaction->kitchen_status === 'pending')
                        <form method="POST" action="{{ route('pos.orders.kitchen', $transaction) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="kitchen_status" value="onprogress">
                            <button class="button button-small button-secondary" type="submit">Mulai dibuat</button>
                        </form>
                    @elseif ($hasKitchenItems && $transaction->kitchen_status === 'onprogress')
                        <form method="POST" action="{{ route('pos.orders.kitchen', $transaction) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="kitchen_status" value="success">
                            <button class="button button-small button-secondary" type="submit">Tandai selesai</button>
                        </form>
                    @endif
                    @if ($transaction->status === 'pending' && (! $hasKitchenItems || $transaction->kitchen_status === 'pending'))
                        <form method="POST" action="{{ route('pos.orders.cancel', $transaction) }}" onsubmit="return confirm('Batalkan pesanan ini?')">
                            @csrf @method('PATCH')
                            <button class="button button-small button-danger" type="submit">Batalkan</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
        @if ($transactions->hasPages())
            <nav class="pagination" aria-label="Halaman transaksi">
                @if ($transactions->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $transactions->previousPageUrl() }}">← Sebelumnya</a> @endif
                <span>Halaman {{ $transactions->currentPage() }} dari {{ $transactions->lastPage() }}</span>
                @if ($transactions->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $transactions->nextPageUrl() }}">Berikutnya →</a> @endif
            </nav>
        @endif
    @endif
</section>

@if (auth()->user()->role === 'manager')
<section class="panel" id="kelola-menu" data-pos-module="menu">
    <div class="panel-head">
        <div><h2>Kelola menu</h2><p>Tambah kategori dan atur ketersediaan menu di katalog pelanggan.</p></div>
    </div>
    <div class="menu-actions-row">
        <details>
            <summary class="button"><x-icon name="add-circle-outline" /> Tambah menu</summary>
            <form class="pos-order-form" method="POST" action="{{ route('pos.menus.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="field-group">
                    <label class="field-label" for="menu_name">Nama menu</label>
                    <input class="field" id="menu_name" name="name" maxlength="120" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="menu_category">Kategori</label>
                    <select class="field" id="menu_category" name="category_id" required>
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="menu_price">Harga (Rupiah)</label>
                    <input class="field" id="menu_price" name="selling_price" type="number" min="1" max="9999999999" step="1" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="menu_description">Deskripsi (opsional)</label>
                    <textarea class="field" id="menu_description" name="description" rows="2" maxlength="500"></textarea>
                </div>
                <div class="field-group">
                    <label class="field-label" for="menu_image">Foto menu (opsional)</label>
                    <input class="field" id="menu_image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-preview-input data-image-preview-target="new-menu-image-preview">
                    <small class="cart-hint">JPG, PNG, atau WebP, maksimal 5 MB. Foto otomatis diperkecil ke maksimal 1.200 px dan dikompresi.</small>
                    <img class="upload-preview" id="new-menu-image-preview" alt="Pratinjau foto menu" hidden>
                </div>
                <div class="field-group">
                    <label class="field-label" for="menu_barcode">Barcode (opsional)</label>
                    <input class="field" id="menu_barcode" name="barcode" maxlength="120" autocomplete="off">
                </div>
                <button class="button" type="submit">Simpan menu</button>
            </form>
        </details>
    </div>
    <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <form class="pos-order-form" method="POST" action="{{ route('pos.categories.store') }}">
            @csrf
            <h3 style="margin:0 0 15px;font-size:15px">Tambah kategori</h3>
            <div class="field-group">
                <label class="field-label" for="category_name">Nama kategori</label>
                <input class="field" id="category_name" name="name" maxlength="80" placeholder="Contoh: Minuman" required>
            </div>
            <button class="button button-secondary" type="submit">Simpan kategori</button>
        </form>
    </div>
    <div class="table-wrap" style="margin-top:24px">
        <p class="menu-cost-note">HPP adalah estimasi dari stok bahan yang masih tersedia dan biaya per satuannya; margin = (harga jual − HPP) / harga jual, sebelum promo dan charge. Jika resep atau biaya stok belum lengkap, HPP dan margin ditandai belum dapat dihitung.</p>
        <table class="menu-table">
            <thead><tr><th>Menu</th><th>Kategori</th><th>Harga jual</th><th>Estimasi HPP</th><th>Margin</th><th>Barcode</th><th>Status</th><th></th><th></th></tr></thead>
            <tbody>
                @forelse ($menus as $menu)
                    <tr>
                        <td><strong>{{ $menu->name }}</strong>@if ($menu->is_bundle) <span class="status-tag">Paket</span>@endif</td>
                        <td>{{ $menu->category->name }}</td>
                        <td>Rp{{ number_format($menu->selling_price, 0, ',', '.') }}</td>
                        <td>
                            @if ($menuCostEstimates->get($menu->id)['cost'] !== null)
                                Rp{{ number_format($menuCostEstimates->get($menu->id)['cost'], 0, ',', '.') }}
                            @else
                                <span class="cart-hint">Belum dapat dihitung</span>
                            @endif
                        </td>
                        <td>
                            @if ($menuCostEstimates->get($menu->id)['margin_percent'] !== null)
                                {{ number_format($menuCostEstimates->get($menu->id)['margin_percent'], 1, ',', '.') }}%
                            @else
                                <span class="cart-hint">—</span>
                            @endif
                        </td>
                        <td>{{ $menu->barcode ?: '—' }}</td>
                        <td><span class="status-tag {{ $menu->is_available ? 'paid' : 'cancelled' }}">{{ $menu->is_available ? 'Tersedia' : 'Disembunyikan' }}</span></td>
                        <td>
                            <details data-menu-editor data-menu-id="{{ $menu->id }}" @if ((int) session('open_menu') === $menu->id) data-open-after-submit @endif>
                                <summary class="button button-secondary button-small">Detail / resep</summary>
                                <div class="menu-editor-grid">
                                <form class="pos-order-form" method="POST" action="{{ route('pos.menus.update', $menu) }}" enctype="multipart/form-data" style="min-width:245px;margin-top:8px">
                                    @csrf @method('PATCH')
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-name-{{ $menu->id }}">Nama</label>
                                        <input class="field" id="edit-menu-name-{{ $menu->id }}" name="name" value="{{ $menu->name }}" maxlength="120" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-category-{{ $menu->id }}">Kategori</label>
                                        <select class="field" id="edit-menu-category-{{ $menu->id }}" name="category_id" required>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}" @selected($menu->category_id === $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-price-{{ $menu->id }}">Harga (Rupiah)</label>
                                        <input class="field" id="edit-menu-price-{{ $menu->id }}" name="selling_price" type="number" min="1" max="9999999999" step="1" value="{{ $menu->selling_price }}" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-description-{{ $menu->id }}">Deskripsi</label>
                                        <textarea class="field" id="edit-menu-description-{{ $menu->id }}" name="description" rows="2" maxlength="500">{{ $menu->description }}</textarea>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-image-{{ $menu->id }}">Foto menu (opsional)</label>
                                        @if ($menu->image_path)
                                            <img class="upload-preview" src="{{ asset($menu->image_path) }}" alt="Foto {{ $menu->name }}">
                                            <label class="field-label"><input type="checkbox" name="remove_image" value="1"> Hapus foto saat disimpan</label>
                                        @endif
                                        <input class="field" id="edit-menu-image-{{ $menu->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-preview-input data-image-preview-target="edit-menu-image-preview-{{ $menu->id }}">
                                        <img class="upload-preview" id="edit-menu-image-preview-{{ $menu->id }}" alt="Pratinjau foto menu" hidden>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-menu-barcode-{{ $menu->id }}">Barcode (opsional)</label>
                                        <input class="field" id="edit-menu-barcode-{{ $menu->id }}" name="barcode" value="{{ $menu->barcode }}" maxlength="120" autocomplete="off">
                                    </div>
                                    <button class="button button-small" type="submit">Simpan perubahan</button>
                                </form>
                                @if ($menu->is_bundle)
                                    <p class="cart-hint">Komponen paket: {{ $menu->bundleItems->pluck('componentMenu.name')->filter()->join(' + ') }}.</p>
                                    <a class="button button-secondary button-small" href="{{ route('pos.bundles.index') }}">Atur komponen paket</a>
                                @else
                                <div class="recipe-editor-column">
                                <form class="pos-order-form" method="POST" action="{{ route('pos.menus.recipes.store', $menu) }}">
                                    @csrf
                                    <h3 style="margin:0 0 12px;font-size:13px">Tambah bahan resep</h3>
                                    <div class="field-group">
                                        <label class="field-label" for="recipe-material-{{ $menu->id }}">Bahan</label>
                                        <select class="field" id="recipe-material-{{ $menu->id }}" name="material_id" required>
                                            <option value="">Pilih bahan</option>
                                            @foreach ($materials as $material)
                                                @if (! $menu->recipes->contains('material_id', $material->id))
                                                    <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit->name }})</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="recipe-quantity-{{ $menu->id }}">Jumlah per porsi</label>
                                        <input class="field" id="recipe-quantity-{{ $menu->id }}" name="quantity" type="number" min="0.001" max="49999999.999" step="0.001" required>
                                    </div>
                                    <button class="button button-small button-secondary" type="submit">Tambahkan resep</button>
                                </form>
                                @if ($menu->recipes->isNotEmpty())
                                    <div class="table-wrap recipe-table-wrap">
                                    <table class="menu-table recipe-table">
                                        <thead><tr><th>Bahan</th><th>Pemakaian / porsi</th><th>HPP / bahan</th><th>Hapus</th></tr></thead>
                                        <tbody>
                                        @foreach ($menu->recipes as $recipe)
                                            @php
                                                $recipeUnitCost = $materialUnitCosts->get($recipe->material_id);
                                                $recipeCost = $recipeUnitCost === null ? null : (float) $recipe->quantity * (float) $recipeUnitCost;
                                            @endphp
                                            <tr>
                                                <td>{{ $recipe->material->name }}</td>
                                                <td>{{ rtrim(rtrim(number_format((float) $recipe->quantity, 3, ',', '.'), '0'), ',') }} {{ $recipe->material->unit->name }}</td>
                                                <td>{{ $recipeCost === null ? 'Belum diketahui' : 'Rp'.number_format($recipeCost, 0, ',', '.') }}</td>
                                                <td>
                                                <form method="POST" action="{{ route('pos.menus.recipes.destroy', [$menu, $recipe]) }}" onsubmit="return confirm('Hapus bahan dari resep menu ini?')">
                                                    @csrf @method('DELETE')
                                                    <button class="button button-danger button-small" type="submit">Hapus</button>
                                                </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                    </div>
                                @else
                                    <p class="cart-hint" style="margin:10px 0">Belum ada resep; stok menu ini belum dilacak.</p>
                                @endif
                                <div class="menu-variants-editor">
                                    <h3>Varian & resep tambahan</h3>
                                    <p class="cart-hint">Resep varian selalu menambahkan bahan di atas resep dasar. Harga tambahan menambah harga jual menu.</p>
                                    <form class="inline-form" method="POST" action="{{ route('pos.menus.variants.store', $menu) }}">
                                        @csrf
                                        <input class="field" name="name" maxlength="80" placeholder="Nama varian, mis. Large" aria-label="Nama varian" required>
                                        <input class="field" name="additional_price" type="number" min="0" max="999999999999" step="1" placeholder="Tambahan harga (Rp)" aria-label="Tambahan harga" required>
                                        <button class="button button-small" type="submit">Tambah varian</button>
                                    </form>
                                    @forelse ($menu->variants as $menuVariant)
                                        <article class="variant-editor-card" data-open-variant="{{ (int) session('open_variant') === $menuVariant->id ? 'true' : 'false' }}">
                                            <div class="variant-editor-head">
                                                <div>
                                                    <strong>{{ $menuVariant->name }}</strong>
                                                    <p class="order-meta">Harga: Rp{{ number_format($menu->selling_price + $menuVariant->additional_price, 0, ',', '.') }} (tambahan Rp{{ number_format($menuVariant->additional_price, 0, ',', '.') }}) · HPP estimasi: {{ $menuVariant->estimated_hpp === null ? 'Belum diketahui' : 'Rp'.number_format($menuVariant->estimated_hpp, 0, ',', '.') }}</p>
                                                </div>
                                                <div class="inline-form">
                                                    <form method="POST" action="{{ route('pos.menus.variants.toggle', [$menu, $menuVariant]) }}">
                                                        @csrf @method('PATCH')
                                                        <button class="button button-small button-secondary" type="submit">{{ $menuVariant->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('pos.menus.variants.destroy', [$menu, $menuVariant]) }}" onsubmit="return confirm('Hapus varian {{ $menuVariant->name }}?')">
                                                        @csrf @method('DELETE')
                                                        <button class="button button-small button-danger" type="submit">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                            <form class="inline-form" method="POST" action="{{ route('pos.menus.variants.recipes.store', [$menu, $menuVariant]) }}">
                                                @csrf
                                                <select class="field" name="material_id" aria-label="Bahan tambahan varian" required>
                                                    <option value="">Pilih bahan tambahan</option>
                                                    @foreach ($materials as $material)
                                                        @if (! $menuVariant->recipes->contains('material_id', $material->id))
                                                            <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit->name }})</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <input class="field" name="quantity" type="number" min="0.001" max="49999999.999" step="0.001" placeholder="Jumlah tambahan / porsi" aria-label="Jumlah bahan tambahan per porsi" required>
                                                <button class="button button-small button-secondary" type="submit">Tambah bahan</button>
                                            </form>
                                            @if ($menuVariant->recipes->isNotEmpty())
                                                <div class="table-wrap recipe-table-wrap">
                                                    <table class="menu-table recipe-table">
                                                        <thead><tr><th>Nama bahan tambahan</th><th>Jumlah</th><th>HPP bahan</th><th></th></tr></thead>
                                                        <tbody>
                                                            @foreach ($menuVariant->recipes as $variantRecipe)
                                                                @php
                                                                    $unitCost = $materialUnitCosts->get($variantRecipe->material_id);
                                                                    $variantRecipeCost = $unitCost === null ? null : (float) $variantRecipe->quantity * (float) $unitCost;
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $variantRecipe->material->name }}</td>
                                                                    <td>{{ rtrim(rtrim(number_format((float) $variantRecipe->quantity, 3, ',', '.'), '0'), ',') }} {{ $variantRecipe->material->unit->name }}</td>
                                                                    <td>{{ $variantRecipeCost === null ? 'Belum diketahui' : 'Rp'.number_format($variantRecipeCost, 0, ',', '.') }}</td>
                                                                    <td>
                                                                        <form method="POST" action="{{ route('pos.menus.variants.recipes.destroy', [$menu, $menuVariant, $variantRecipe]) }}" onsubmit="return confirm('Hapus bahan tambahan ini?')">
                                                                            @csrf @method('DELETE')
                                                                            <button class="button button-danger button-small" type="submit">Hapus</button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <p class="cart-hint">Belum ada bahan tambahan; varian memakai resep dasar saja.</p>
                                            @endif
                                        </article>
                                    @empty
                                        <p class="cart-hint">Menu ini belum memiliki varian.</p>
                                    @endforelse
                                </div>
                                </div>
                                @endif
                                </div>
                            </details>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('pos.menus.toggle', $menu) }}">
                                @csrf @method('PATCH')
                                <button class="button button-small button-secondary" type="submit">{{ $menu->is_available ? 'Sembunyikan' : 'Tampilkan' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">Belum ada menu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" id="kelola-bahan" data-pos-module="stok">
    <div class="panel-head">
        <div><h2>Stok bahan & resep</h2><p>Masukkan bahan, catat stok per batch, lalu hubungkan bahan dengan menu. Stok berkurang saat transaksi dibayar.</p></div>
        <span class="status-tag {{ $materials->contains(fn ($material) => $material->is_below_minimum) ? 'pending' : 'paid' }}">
            {{ $materials->contains(fn ($material) => $material->is_below_minimum) ? 'Stok minimum perlu dicek' : 'Stok terkendali' }}
        </span>
    </div>
    <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <form class="pos-order-form" method="POST" action="{{ route('pos.materials.store') }}" data-material-cost-form>
            @csrf
            <h3 style="margin:0 0 15px;font-size:15px">Tambah bahan & stok awal</h3>
            <div class="field-group">
                <label class="field-label" for="material_name">Nama bahan</label>
                <input class="field" id="material_name" name="name" maxlength="120" placeholder="Contoh: biji kopi" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="material_unit">Satuan</label>
                <select class="field" id="material_unit" name="unit_id" required>
                    <option value="">Pilih satuan</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-label" for="material_supplier">Supplier (opsional)</label>
                <select class="field" id="material_supplier" name="supplier_id">
                    <option value="">Tanpa supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-label" for="material_minimum">Batas peringatan stok minimum</label>
                <input class="field" id="material_minimum" name="minimum_quantity" type="number" min="0" max="999999999.999" step="0.001" value="0" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="material-initial-quantity">Jumlah stok awal (opsional)</label>
                <input class="field" id="material-initial-quantity" name="initial_quantity" type="number" min="0.001" max="999999999.999" step="0.001" data-cost-quantity>
            </div>
            <div class="field-group">
                <label class="field-label" for="material-initial-cost">Harga total stok awal (Rp, opsional)</label>
                <input class="field" id="material-initial-cost" name="initial_total_cost" type="number" min="0" max="999999999999" step="1" data-total-cost>
            </div>
            <div class="field-group">
                <label class="field-label" for="material-initial-unit-cost">HPP per satuan</label>
                <input class="field" id="material-initial-unit-cost" type="text" value="Masukkan jumlah dan total harga" readonly data-unit-cost-preview aria-live="polite">
            </div>
            <button class="button button-secondary" type="submit">Simpan bahan</button>
        </form>
        <div class="pos-order-form">
            <h3 style="margin:0 0 12px;font-size:15px">Cara kerja stok</h3>
            <p class="menu-description" style="font-size:13px">Stok dicatat per batch. Bahan yang kedaluwarsa dikecualikan, dan batch dengan tanggal kedaluwarsa terdekat dipakai lebih dahulu. Transaksi belum dibayar tidak mengurangi stok.</p>
            <p class="menu-description" style="font-size:13px;margin-top:10px">Menu tanpa resep tetap dijual tanpa pelacakan bahan. Tambahkan resep pada bagian edit menu untuk menghitung ketersediaan dan pemakaian otomatis.</p>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:24px">
        <table class="menu-table">
            <thead><tr><th>Bahan</th><th>Satuan</th><th>Supplier</th><th>HPP / satuan</th><th>Stok tersedia</th><th>Minimum</th><th>Status</th><th>Detail bahan & stok masuk</th></tr></thead>
            <tbody>
                @forelse ($materials as $material)
                    <tr>
                        <td><strong>{{ $material->name }}</strong></td>
                        <td>{{ $material->unit->name }}</td>
                        <td>{{ $material->supplier?->name ?? '—' }}</td>
                        <td>{{ $material->estimated_unit_cost === null ? 'Belum ada biaya' : 'Rp'.number_format($material->estimated_unit_cost, 2, ',', '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($material->stock_on_hand, 3, ',', '.'), '0'), ',') }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $material->minimum_quantity, 3, ',', '.'), '0'), ',') }}</td>
                        <td><span class="status-tag {{ $material->is_below_minimum ? 'pending' : 'paid' }}">{{ $material->is_below_minimum ? 'Di bawah minimum' : 'Cukup' }}</span></td>
                        <td>
                            <details>
                                <summary class="button button-secondary button-small">Edit / detail</summary>
                                <form class="pos-order-form" method="POST" action="{{ route('pos.materials.update', $material) }}" style="min-width:260px;margin-top:12px">
                                    @csrf @method('PATCH')
                                    <h3 style="margin:0 0 12px;font-size:14px">Detail bahan</h3>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-material-name-{{ $material->id }}">Nama bahan</label>
                                        <input class="field" id="edit-material-name-{{ $material->id }}" name="name" value="{{ $material->name }}" maxlength="120" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-material-unit-{{ $material->id }}">Satuan</label>
                                        <select class="field" id="edit-material-unit-{{ $material->id }}" name="unit_id" required>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}" @selected($material->unit_id === $unit->id)>{{ $unit->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-material-supplier-{{ $material->id }}">Supplier (opsional)</label>
                                        <select class="field" id="edit-material-supplier-{{ $material->id }}" name="supplier_id">
                                            <option value="">Tanpa supplier</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" @selected($material->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="edit-material-minimum-{{ $material->id }}">Stok minimum</label>
                                        <input class="field" id="edit-material-minimum-{{ $material->id }}" name="minimum_quantity" type="number" min="0" max="999999999.999" step="0.001" value="{{ $material->minimum_quantity }}" required>
                                    </div>
                                    <button class="button button-small" type="submit">Simpan detail</button>
                                </form>
                                <form class="pos-order-form" method="POST" action="{{ route('pos.materials.stock', $material) }}" style="min-width:260px;margin-top:12px" data-material-cost-form>
                                    @csrf
                                    <h3 style="margin:0 0 12px;font-size:14px">Catat stok masuk</h3>
                                    <div class="field-group">
                                        <label class="field-label" for="batch-code-{{ $material->id }}">Kode batch</label>
                                        <input class="field" id="batch-code-{{ $material->id }}" name="batch_code" maxlength="64" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="quantity-{{ $material->id }}">Jumlah ({{ $material->unit->name }})</label>
                                        <input class="field" id="quantity-{{ $material->id }}" name="quantity" type="number" min="0.001" max="999999999.999" step="0.001" required data-cost-quantity>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="total-cost-{{ $material->id }}">Total harga bahan (Rp, opsional)</label>
                                        <input class="field" id="total-cost-{{ $material->id }}" name="total_cost" type="number" min="0" max="999999999999" step="1" data-total-cost>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="unit-cost-{{ $material->id }}">HPP per {{ $material->unit->name }}</label>
                                        <input class="field" id="unit-cost-{{ $material->id }}" type="text" value="Masukkan jumlah dan total harga" readonly data-unit-cost-preview aria-live="polite">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="expires-{{ $material->id }}">Kedaluwarsa (opsional)</label>
                                        <input class="field" id="expires-{{ $material->id }}" name="expires_at" type="date">
                                    </div>
                                    <button class="button button-small" type="submit">Simpan stok masuk</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Belum ada bahan. Tambahkan bahan untuk mulai melacak stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" id="meja" data-pos-module="meja">
    <div class="panel-head">
        <div><h2>Meja & QR</h2><p>Pelanggan yang memindai QR meja akan memesan dengan meja terikat ke sesi.</p></div>
    </div>
    <form class="inline-form" method="POST" action="{{ route('pos.tables.store') }}">
        @csrf
        <div class="field-group">
            <label class="field-label" for="table-number">Nomor meja</label>
            <input class="field" id="table-number" name="number" maxlength="32" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="table-capacity">Kapasitas</label>
            <input class="field" id="table-capacity" name="capacity" type="number" min="1" max="100" value="2" required>
        </div>
        <button class="button" type="submit">Tambah meja</button>
    </form>
    @if ($diningTables->isEmpty())
        <div class="empty-state">Belum ada meja. Tambahkan meja untuk membuat QR pemesanan.</div>
    @else
        <div class="menu-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:20px">
            @foreach ($diningTables as $table)
                <article class="menu-card" style="padding:18px">
                    <h3 style="margin:0">Meja {{ $table->number }}</h3>
                    <p class="menu-description">{{ $table->capacity }} kursi · {{ $table->transactions_count }} pesanan sepanjang waktu</p>
                    <span class="status-tag {{ $table->status === 'occupied' ? 'pending' : 'paid' }}">{{ $table->status === 'occupied' ? 'Terisi' : 'Tersedia' }}</span>
                    <div style="margin:14px 0">
                        <canvas data-qr-value="{{ route('tables.enter', $table->qr_token) }}" data-table-number="{{ $table->number }}" aria-label="QR untuk meja {{ $table->number }}"></canvas>
                        <p data-qr-error class="cart-hint" role="status"></p>
                    </div>
                    <a class="button button-secondary button-small" data-qr-download href="#">Unduh QR PNG</a>
                    @if ($table->status === 'occupied')
                        <form method="POST" action="{{ route('pos.tables.clear', $table) }}" style="margin-top:12px">
                            @csrf @method('PATCH')
                            <button class="button button-small" type="submit">Tandai tersedia</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="panel" id="kupon" data-pos-module="kupon">
    <div class="panel-head">
        <div><h2>Kupon diskon</h2><p>Kupon berlaku saat pemesanan dan divalidasi ulang oleh server saat transaksi dibuat.</p></div>
    </div>
    <form class="inline-form" method="POST" action="{{ route('pos.coupons.store') }}">
        @csrf
        <div class="field-group">
            <label class="field-label" for="new-coupon-code">Kode</label>
            <input class="field" id="new-coupon-code" name="code" maxlength="40" pattern="[A-Za-z0-9_-]+" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-coupon-type">Jenis diskon</label>
            <select class="field" id="new-coupon-type" name="type" required>
                <option value="percentage">Persentase</option>
                <option value="fixed">Potongan Rupiah</option>
            </select>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-coupon-value">Nilai</label>
            <input class="field" id="new-coupon-value" name="value" type="number" min="1" step="1" required>
        </div>
        <button class="button" type="submit">Tambah kupon</button>
    </form>
    @if ($coupons->isEmpty())
        <div class="empty-state">Belum ada kupon.</div>
    @else
        <div class="table-wrap" style="margin-top:18px">
            <table class="menu-table">
                <thead><tr><th>Kode</th><th>Aturan</th><th>Status & perubahan</th></tr></thead>
                <tbody>
                    @foreach ($coupons as $coupon)
                        <tr>
                            <td><strong>{{ $coupon->code }}</strong></td>
                            <td>{{ $coupon->type === 'percentage' ? $coupon->value.'%' : 'Rp'.number_format($coupon->value, 0, ',', '.') }}</td>
                            <td>
                                <details>
                                    <summary class="nav-link" style="padding:5px 0;cursor:pointer">{{ $coupon->is_active ? 'Aktif · Edit' : 'Nonaktif · Edit' }}</summary>
                                    <form class="pos-order-form" method="POST" action="{{ route('pos.coupons.update', $coupon) }}" style="min-width:220px;margin-top:8px">
                                        @csrf @method('PATCH')
                                        <div class="field-group">
                                            <label class="field-label" for="coupon-code-{{ $coupon->id }}">Kode</label>
                                            <input class="field" id="coupon-code-{{ $coupon->id }}" name="code" value="{{ $coupon->code }}" maxlength="40" pattern="[A-Za-z0-9_-]+" required>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="coupon-type-{{ $coupon->id }}">Jenis</label>
                                            <select class="field" id="coupon-type-{{ $coupon->id }}" name="type" required>
                                                <option value="percentage" @selected($coupon->type === 'percentage')>Persentase</option>
                                                <option value="fixed" @selected($coupon->type === 'fixed')>Potongan Rupiah</option>
                                            </select>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="coupon-value-{{ $coupon->id }}">Nilai</label>
                                            <input class="field" id="coupon-value-{{ $coupon->id }}" name="value" type="number" min="1" step="1" value="{{ $coupon->value }}" required>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="coupon-active-{{ $coupon->id }}">Status</label>
                                            <select class="field" id="coupon-active-{{ $coupon->id }}" name="is_active" required>
                                                <option value="1" @selected($coupon->is_active)>Aktif</option>
                                                <option value="0" @selected(! $coupon->is_active)>Nonaktif</option>
                                            </select>
                                        </div>
                                        <button class="button button-small" type="submit">Simpan kupon</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="panel" id="pelanggan" data-pos-module="pelanggan">
    <div class="panel-head">
        <div><h2>Pelanggan</h2><p>Profil tersimpan dari nomor telepon; pesanan berikutnya akan dikaitkan ke profil yang sama.</p></div>
        <span class="status-tag">{{ $customers->count() }} pelanggan terbaru</span>
    </div>
    @if ($customers->isEmpty())
        <div class="empty-state">Belum ada pelanggan. Profil akan tercatat saat pesanan pertama dibuat dengan nomor telepon.</div>
    @else
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Nama</th><th>Telepon</th><th>Jumlah transaksi</th><th>Terdaftar</th></tr></thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td><strong>{{ $customer->name }}</strong></td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $customer->transactions_count }}</td>
                            <td>{{ $customer->created_at->locale('id')->translatedFormat('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endif
</div>
</div>
@endsection
