@extends('layouts.app')

@section('title', 'Pembelian & pemasok')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pengadaan</p>
        <h1>Pembelian stok.</h1>
        <p>Kelola pemasok, tagihan pembelian, penerimaan barang, dan pembayaran.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
        <a class="button button-secondary" href="{{ route('pos.purchase-returns.index') }}">Retur pembelian</a>
        <a class="button button-secondary" href="#buat-pembelian">Buat pembelian</a>
    </div>
</div>

<section class="panel" id="pemasok">
    <div class="panel-head">
        <div><h2>Pemasok</h2><p>Data pemasok dipakai untuk mencatat pembelian dan riwayat tagihan.</p></div>
        <span class="status-tag">{{ $suppliers->count() }} pemasok</span>
    </div>
    <form class="pos-layout" method="POST" action="{{ route('pos.suppliers.store') }}" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        @csrf
        <div class="pos-order-form">
            <h3 style="margin:0 0 15px;font-size:15px">Tambah pemasok</h3>
            <div class="field-group">
                <label class="field-label" for="supplier-code">Kode pemasok</label>
                <input class="field" id="supplier-code" name="code" maxlength="40" pattern="[A-Za-z0-9_-]+" placeholder="Contoh: SPR-01" value="{{ old('code') }}" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="supplier-name">Nama pemasok</label>
                <input class="field" id="supplier-name" name="name" maxlength="120" value="{{ old('name') }}" required>
            </div>
        </div>
        <div class="pos-order-form">
            <h3 style="margin:0 0 15px;font-size:15px">Kontak (opsional)</h3>
            <div class="field-group">
                <label class="field-label" for="supplier-phone">Telepon</label>
                <input class="field" id="supplier-phone" name="phone" maxlength="40" value="{{ old('phone') }}">
            </div>
            <div class="field-group">
                <label class="field-label" for="supplier-email">Email</label>
                <input class="field" id="supplier-email" name="email" type="email" maxlength="255" value="{{ old('email') }}">
            </div>
            <div class="field-group">
                <label class="field-label" for="supplier-address">Alamat</label>
                <textarea class="field" id="supplier-address" name="address" rows="2" maxlength="2000">{{ old('address') }}</textarea>
            </div>
            <button class="button" type="submit">Simpan pemasok</button>
        </div>
    </form>

    @if ($suppliers->isNotEmpty())
        <div class="table-wrap" style="margin-top:20px">
            <table class="menu-table">
                <thead><tr><th>Kode</th><th>Pemasok</th><th>Kontak</th><th>Alamat</th><th></th></tr></thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->code }}</strong></td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone ?: '—' }}{{ $supplier->email ? ' · '.$supplier->email : '' }}</td>
                            <td>{{ $supplier->address ?: '—' }}</td>
                            <td>
                                <details>
                                    <summary class="nav-link" style="padding:5px 0;cursor:pointer">Edit</summary>
                                    <form class="pos-order-form" method="POST" action="{{ route('pos.suppliers.update', $supplier) }}" style="min-width:245px;margin-top:8px">
                                        @csrf @method('PATCH')
                                        <div class="field-group">
                                            <label class="field-label" for="supplier-code-{{ $supplier->id }}">Kode</label>
                                            <input class="field" id="supplier-code-{{ $supplier->id }}" name="code" value="{{ $supplier->code }}" maxlength="40" pattern="[A-Za-z0-9_-]+" required>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="supplier-name-{{ $supplier->id }}">Nama</label>
                                            <input class="field" id="supplier-name-{{ $supplier->id }}" name="name" value="{{ $supplier->name }}" maxlength="120" required>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="supplier-phone-{{ $supplier->id }}">Telepon</label>
                                            <input class="field" id="supplier-phone-{{ $supplier->id }}" name="phone" value="{{ $supplier->phone }}" maxlength="40">
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="supplier-email-{{ $supplier->id }}">Email</label>
                                            <input class="field" id="supplier-email-{{ $supplier->id }}" name="email" type="email" value="{{ $supplier->email }}" maxlength="255">
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label" for="supplier-address-{{ $supplier->id }}">Alamat</label>
                                            <textarea class="field" id="supplier-address-{{ $supplier->id }}" name="address" rows="2" maxlength="2000">{{ $supplier->address }}</textarea>
                                        </div>
                                        <button class="button button-small" type="submit">Simpan perubahan</button>
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

<section class="panel" id="buat-pembelian">
    <div class="panel-head">
        <div><h2>Buat pembelian bahan / produk eceran</h2><p>Pilih bahan resep atau produk eceran. Satu dokumen hanya memuat satu jenis; stok bertambah setelah barang diterima.</p></div>
    </div>
    @if ($suppliers->isEmpty() || ($materials->isEmpty() && $productVariants->isEmpty()))
        <div class="empty-state">Tambahkan pemasok dan setidaknya satu bahan atau varian produk sebelum membuat pembelian.</div>
    @else
        <form class="pos-order-form" method="POST" action="{{ route('pos.purchases.store') }}" data-purchase-form>
            @csrf
            <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="field-group">
                    <label class="field-label" for="purchase-type">Jenis barang</label>
                    <select class="field" id="purchase-type" name="purchase_type" data-purchase-type required>
                        <option value="materials" @selected($oldPurchaseType === 'materials') @disabled($materials->isEmpty())>Bahan resep</option>
                        <option value="products" @selected($oldPurchaseType === 'products') @disabled($productVariants->isEmpty())>Produk eceran</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="purchase-supplier">Pemasok</label>
                    <select class="field" id="purchase-supplier" name="supplier_id" required>
                        <option value="">Pilih pemasok</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="purchase-date">Tanggal pembelian</label>
                    <input class="field" id="purchase-date" name="purchase_date" type="date" value="{{ old('purchase_date', today()->toDateString()) }}" required>
                </div>
            </div>

            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Item</th><th>Jumlah</th><th>Harga per satuan (Rp)</th><th>Kedaluwarsa</th><th></th></tr></thead>
                    <tbody data-purchase-items>
                        @foreach ($oldItems as $index => $item)
                            <tr data-purchase-item>
                                <td>
                                    @php
                                        $selectedItem = $item['item_ref'] ?? (filled($item['material_id'] ?? null) ? 'material:'.$item['material_id'] : '');
                                    @endphp
                                    <label class="visually-hidden" for="purchase-item-{{ $index }}">Item</label>
                                    <select class="field" id="purchase-item-{{ $index }}" name="items[{{ $index }}][item_ref]" data-purchase-item-select required>
                                        <option value="">Pilih item</option>
                                        @foreach ($materials as $material)
                                            <option value="material:{{ $material->id }}" data-item-type="materials" @selected($selectedItem === 'material:'.$material->id)>{{ $material->name }} ({{ $material->unit->name }})</option>
                                        @endforeach
                                        @foreach ($productVariants as $variant)
                                            <option value="product:{{ $variant->id }}" data-item-type="products" @selected($selectedItem === 'product:'.$variant->id)>{{ $variant->product->name }} · {{ $variant->label }} ({{ $variant->unit->name }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><label class="visually-hidden" for="purchase-quantity-{{ $index }}">Jumlah</label><input class="field" id="purchase-quantity-{{ $index }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" value="{{ $item['quantity'] ?? '1' }}" required></td>
                                <td><label class="visually-hidden" for="purchase-cost-{{ $index }}">Harga per satuan</label><input class="field" id="purchase-cost-{{ $index }}" name="items[{{ $index }}][unit_cost]" type="number" min="1" max="999999999999" step="1" value="{{ $item['unit_cost'] ?? '' }}" required></td>
                                <td><label class="visually-hidden" for="purchase-expiry-{{ $index }}">Kedaluwarsa</label><input class="field" id="purchase-expiry-{{ $index }}" name="items[{{ $index }}][expires_at]" type="date" value="{{ $item['expires_at'] ?? '' }}"></td>
                                <td><button class="button button-danger button-small" type="button" data-remove-purchase-item aria-label="Hapus baris bahan">Hapus</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-purchase-template>
                <tr data-purchase-item>
                    <td>
                        <label class="visually-hidden" for="purchase-item-__INDEX__">Item</label>
                        <select class="field" id="purchase-item-__INDEX__" name="items[__INDEX__][item_ref]" data-purchase-item-select required>
                            <option value="">Pilih item</option>
                            @foreach ($materials as $material)
                                <option value="material:{{ $material->id }}" data-item-type="materials">{{ $material->name }} ({{ $material->unit->name }})</option>
                            @endforeach
                            @foreach ($productVariants as $variant)
                                <option value="product:{{ $variant->id }}" data-item-type="products">{{ $variant->product->name }} · {{ $variant->label }} ({{ $variant->unit->name }})</option>
                            @endforeach
                        </select>
                    </td>
                    <td><label class="visually-hidden" for="purchase-quantity-__INDEX__">Jumlah</label><input class="field" id="purchase-quantity-__INDEX__" name="items[__INDEX__][quantity]" type="number" min="0.001" max="999999999.999" step="0.001" value="1" required></td>
                    <td><label class="visually-hidden" for="purchase-cost-__INDEX__">Harga per satuan</label><input class="field" id="purchase-cost-__INDEX__" name="items[__INDEX__][unit_cost]" type="number" min="1" max="999999999999" step="1" required></td>
                    <td><label class="visually-hidden" for="purchase-expiry-__INDEX__">Kedaluwarsa</label><input class="field" id="purchase-expiry-__INDEX__" name="items[__INDEX__][expires_at]" type="date"></td>
                    <td><button class="button button-danger button-small" type="button" data-remove-purchase-item aria-label="Hapus baris bahan">Hapus</button></td>
                </tr>
            </template>
            <button class="button button-secondary button-small" type="button" data-add-purchase-item>Tambah item</button>
            <p class="cart-hint" data-purchase-estimate role="status" style="margin-top:12px">Perkiraan subtotal: Rp0. Nilai akhir dihitung di server.</p>

            <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:18px">
                <div>
                    <div class="field-group">
                        <label class="field-label" for="discount-type">Jenis diskon</label>
                        <select class="field" id="discount-type" name="discount_type">
                            <option value="">Tanpa diskon</option>
                            <option value="percentage" @selected(old('discount_type') === 'percentage')>Persentase</option>
                            <option value="fixed" @selected(old('discount_type') === 'fixed')>Potongan Rupiah</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="discount-value">Nilai diskon</label>
                        <input class="field" id="discount-value" name="discount_value" type="number" min="0" max="999999999999" step="1" value="{{ old('discount_value', 0) }}">
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="purchase-notes">Catatan</label>
                        <textarea class="field" id="purchase-notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                    </div>
                </div>
                <div>
                    <div class="field-group">
                        <label class="field-label" for="initial-payment">Pembayaran awal (opsional)</label>
                        <input class="field" id="initial-payment" name="initial_payment" type="number" min="0" max="999999999999" step="1" value="{{ old('initial_payment') }}" placeholder="Kosongkan jika belum dibayar">
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="initial-payment-method">Metode pembayaran</label>
                        <select class="field" id="initial-payment-method" name="payment_method">
                            <option value="">Belum ada pembayaran</option>
                            <option value="cash" @selected(old('payment_method') === 'cash')>Tunai</option>
                            <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                        </select>
                    </div>
                    <p class="cart-hint">Status awal menunggu konfirmasi. Pembayaran awal dicatat sebagai cicilan; sisa tagihan dapat dibayar kemudian.</p>
                </div>
            </div>
            <button class="button" type="submit">Simpan pembelian</button>
        </form>
    @endif
</section>

<section class="panel" id="riwayat-pembelian">
    <div class="panel-head">
        <div><h2>Riwayat pembelian</h2><p>Konfirmasi pesanan, catat penerimaan bahan, lalu rekam pembayaran pemasok.</p></div>
        <span class="status-tag">{{ $purchases->total() }} pembelian</span>
    </div>
    <form method="GET" action="{{ route('pos.purchases.index') }}" class="filter-row" style="align-items:end">
        <div style="flex:1 1 190px">
            <label class="field-label" for="purchase-search">Cari pembelian atau pemasok</label>
            <input class="field" id="purchase-search" name="search" value="{{ $filters['search'] }}" placeholder="Kode pembelian, nama pemasok">
        </div>
        <div>
            <label class="field-label" for="purchase-status">Penerimaan</label>
            <select class="field" id="purchase-status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua status</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Menunggu konfirmasi</option>
                <option value="confirmed" @selected($filters['status'] === 'confirmed')>Menunggu barang</option>
                <option value="received" @selected($filters['status'] === 'received')>Barang diterima</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="purchase-payment-status">Pembayaran</label>
            <select class="field" id="purchase-payment-status" name="payment_status">
                <option value="all" @selected($filters['payment_status'] === 'all')>Semua status</option>
                <option value="unpaid" @selected($filters['payment_status'] === 'unpaid')>Belum dibayar</option>
                <option value="partial" @selected($filters['payment_status'] === 'partial')>Dibayar sebagian</option>
                <option value="paid" @selected($filters['payment_status'] === 'paid')>Lunas</option>
            </select>
        </div>
        <button class="button" type="submit">Terapkan</button>
        <a class="button button-secondary" href="{{ route('pos.purchases.index') }}">Reset</a>
    </form>
    @if ($purchases->isEmpty())
        <div class="empty-state">Belum ada pembelian yang tercatat.</div>
    @else
        @foreach ($purchases as $purchase)
            @php
                $paid = (int) $purchase->payments->sum('amount');
                $debtCredited = (int) $purchase->returns
                    ->where('settlement_method', 'debt_reduction')
                    ->where('status', 'confirmed')
                    ->sum('amount');
                $debtReserved = (int) $purchase->returns
                    ->where('settlement_method', 'debt_reduction')
                    ->where('status', 'pending')
                    ->sum('amount');
                $remaining = max(0, $purchase->grand_total - $paid - $debtCredited - $debtReserved);
                $purchaseStatus = ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Menunggu barang', 'received' => 'Barang diterima'][$purchase->status] ?? $purchase->status;
                $paymentStatus = ['unpaid' => 'Belum dibayar', 'partial' => 'Dibayar sebagian', 'paid' => 'Lunas'][$purchase->payment_status] ?? $purchase->payment_status;
            @endphp
            <article class="order-card">
                <div class="order-card-head">
                    <div>
                        <h3 class="order-code">{{ $purchase->purchase_code }}</h3>
                        <p class="order-meta">{{ $purchase->purchase_type === 'products' ? 'Produk eceran' : 'Bahan resep' }} · {{ $purchase->supplier->code }} · {{ $purchase->supplier->name }} · {{ $purchase->purchase_date->locale('id')->translatedFormat('d M Y') }}</p>
                        <p class="order-meta">Total Rp{{ number_format($purchase->grand_total, 0, ',', '.') }} · Dibayar Rp{{ number_format($paid, 0, ',', '.') }} · Pengurangan utang Rp{{ number_format($debtCredited, 0, ',', '.') }}{{ $debtReserved > 0 ? ' · Dicadangkan Rp'.number_format($debtReserved, 0, ',', '.') : '' }} · Sisa Rp{{ number_format($remaining, 0, ',', '.') }}</p>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;justify-content:end;gap:6px">
                        <span class="status-tag {{ $purchase->status === 'received' ? 'paid' : 'pending' }}">{{ $purchaseStatus }}</span>
                        <span class="status-tag {{ $purchase->payment_status === 'paid' ? 'paid' : 'pending' }}">{{ $paymentStatus }}</span>
                    </div>
                </div>
                @if ($purchase->discount > 0)
                    <p class="order-meta">Subtotal Rp{{ number_format($purchase->subtotal, 0, ',', '.') }} · Diskon Rp{{ number_format($purchase->discount, 0, ',', '.') }}</p>
                @endif
                <ul class="order-lines">
                    @foreach ($purchase->items as $item)
                        <li>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->unit_name }} {{ $item->material_name }} × Rp{{ number_format($item->unit_cost, 0, ',', '.') }} = Rp{{ number_format($item->line_total, 0, ',', '.') }}{{ $item->expires_at ? ' · kedaluwarsa '.$item->expires_at->locale('id')->translatedFormat('d M Y') : '' }}</li>
                    @endforeach
                    @if ($purchase->notes)<li>Catatan: {{ $purchase->notes }}</li>@endif
                    @foreach ($purchase->payments as $payment)
                        <li>Pembayaran {{ $payment->paid_at->locale('id')->translatedFormat('d M Y') }} · {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }} · Rp{{ number_format($payment->amount, 0, ',', '.') }}</li>
                    @endforeach
                    @foreach ($purchase->returns->where('settlement_method', 'debt_reduction')->where('status', 'confirmed') as $debtReturn)
                        <li>Pengurangan utang dari retur {{ $debtReturn->return_code }} · Rp{{ number_format($debtReturn->amount, 0, ',', '.') }}</li>
                    @endforeach
                </ul>
                <div class="order-actions">
                    @if ($purchase->status === 'pending')
                        <form method="POST" action="{{ route('pos.purchases.confirm', $purchase) }}">
                            @csrf @method('PATCH')
                            <button class="button button-small" type="submit">Konfirmasi pembelian</button>
                        </form>
                    @elseif ($purchase->status === 'confirmed')
                        <form method="POST" action="{{ route('pos.purchases.receive', $purchase) }}" onsubmit="return confirm('Catat semua item pada pembelian ini sebagai stok masuk?')">
                            @csrf @method('PATCH')
                            <button class="button button-small" type="submit">Terima barang & tambah stok</button>
                        </form>
                    @endif
                    @if ($remaining > 0)
                        <form class="inline-form" method="POST" action="{{ route('pos.purchases.payments.store', $purchase) }}">
                            @csrf
                            <label class="visually-hidden" for="payment-date-{{ $purchase->id }}">Tanggal pembayaran</label>
                            <input class="field" id="payment-date-{{ $purchase->id }}" name="paid_at" type="date" value="{{ today()->toDateString() }}" required>
                            <label class="visually-hidden" for="payment-amount-{{ $purchase->id }}">Jumlah pembayaran</label>
                            <input class="field" id="payment-amount-{{ $purchase->id }}" name="amount" type="number" min="1" max="{{ $remaining }}" placeholder="Maks. {{ $remaining }}" required>
                            <label class="visually-hidden" for="payment-method-{{ $purchase->id }}">Metode pembayaran</label>
                            <select class="field" id="payment-method-{{ $purchase->id }}" name="payment_method" required>
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                            <button class="button button-secondary button-small" type="submit">Catat pembayaran</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
        @if ($purchases->hasPages())
            <nav class="pagination" aria-label="Halaman riwayat pembelian">
                @if ($purchases->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $purchases->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $purchases->currentPage() }} dari {{ $purchases->lastPage() }}</span>
                @if ($purchases->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $purchases->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>
@endsection
