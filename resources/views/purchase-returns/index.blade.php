@extends('layouts.app')

@section('title', 'Retur pembelian')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pengadaan</p>
        <h1>Retur pembelian.</h1>
        <p>Catat barang yang dikembalikan ke pemasok, refund, pengganti, atau pengurangan utang.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.purchases.index') }}">Kembali ke pembelian</a>
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Cari pembelian yang sudah diterima</h2><p>Jumlah yang bisa diretur mencakup retur tertunda dan yang telah dikonfirmasi.</p></div>
    </div>
    <form method="GET" action="{{ route('pos.purchase-returns.index') }}" class="filter-row">
        <div style="flex:1 1 260px">
            <label class="field-label" for="purchase-return-search">Kode pembelian atau pemasok</label>
            <input class="field" id="purchase-return-search" name="search" value="{{ $filters['search'] }}" placeholder="Cari pembelian">
        </div>
        <button class="button" type="submit">Cari pembelian</button>
        <a class="button button-secondary" href="{{ route('pos.purchase-returns.index') }}">Reset</a>
    </form>

    @if ($eligiblePurchases->isEmpty())
        <div class="empty-state">Tidak ada pembelian yang sudah diterima. Terima barang terlebih dahulu sebelum membuat retur.</div>
    @else
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Pembelian</th><th>Pemasok</th><th>Tanggal</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @foreach ($eligiblePurchases as $purchase)
                        <tr>
                            <td><strong>{{ $purchase->purchase_code }}</strong></td>
                            <td>{{ $purchase->supplier->code }} · {{ $purchase->supplier->name }}</td>
                            <td>{{ $purchase->purchase_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>Rp{{ number_format($purchase->grand_total, 0, ',', '.') }}</td>
                            <td><a class="button button-secondary button-small" href="{{ route('pos.purchase-returns.index', array_filter(['search' => $filters['search'], 'status' => $filters['status'], 'purchase_id' => $purchase->id])) }}">Pilih pembelian</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($eligiblePurchases->hasPages())
            <nav class="pagination" aria-label="Halaman pembelian yang bisa diretur">
                @if ($eligiblePurchases->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $eligiblePurchases->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $eligiblePurchases->currentPage() }} dari {{ $eligiblePurchases->lastPage() }}</span>
                @if ($eligiblePurchases->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $eligiblePurchases->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>

@if ($selectedPurchase)
    <section class="panel" id="buat-retur-pembelian">
        <div class="panel-head">
            <div>
                <h2>Ajukan retur · {{ $selectedPurchase->purchase_code }}</h2>
                <p>{{ $selectedPurchase->supplier->name }} · Total Rp{{ number_format($selectedPurchase->grand_total, 0, ',', '.') }}. Refund dan pengurangan utang memakai alokasi diskon pembelian.</p>
            </div>
            <span class="status-tag paid">Barang diterima</span>
        </div>
        <form method="POST" action="{{ route('pos.purchase-returns.store') }}">
            @csrf
            <input type="hidden" name="purchase_id" value="{{ $selectedPurchase->id }}">
            <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="field-group">
                    <label class="field-label" for="purchase-return-date">Tanggal retur</label>
                    <input class="field" id="purchase-return-date" name="return_date" type="date" value="{{ old('return_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="purchase-return-method">Penyelesaian dengan pemasok</label>
                    <select class="field" id="purchase-return-method" name="settlement_method" required>
                        <option value="refund" @selected(old('settlement_method', 'refund') === 'refund')>Refund uang dari pemasok</option>
                        <option value="replacement" @selected(old('settlement_method') === 'replacement')>Barang pengganti</option>
                        <option value="debt_reduction" @selected(old('settlement_method') === 'debt_reduction')>Pengurangan utang pemasok</option>
                    </select>
                </div>
            </div>
            <div class="alert" role="note">Saat dikonfirmasi, jumlah keluar dari batch asal dan stok batch tidak pernah boleh negatif. Pilihan barang pengganti juga membuat batch masuk baru; refund dan pengurangan utang dicatat terpisah dari kas shift.</div>
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Item pembelian</th><th>Diterima</th><th>Sudah diminta / dikonfirmasi</th><th>Sisa dapat diretur</th><th>Jumlah retur</th><th>Kedaluwarsa pengganti</th><th>Alasan</th></tr></thead>
                    <tbody>
                        @foreach ($selectedPurchase->items as $index => $item)
                            @php
                                $reserved = $item->returnDetails
                                    ->filter(fn ($row) => in_array($row->purchaseReturn?->status, ['pending', 'confirmed'], true))
                                    ->sum('quantity');
                                $remaining = max(0, round((float) $item->quantity - (float) $reserved, 3));
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item->material_name }}</strong>
                                    <div class="order-meta">{{ $item->unit_name }} · Rp{{ number_format($item->line_total, 0, ',', '.') }} nilai baris</div>
                                    <input type="hidden" name="items[{{ $index }}][purchase_item_id]" value="{{ $item->id }}">
                                </td>
                                <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->unit_name }}</td>
                                <td>{{ rtrim(rtrim(number_format((float) $reserved, 3, ',', '.'), '0'), ',') }}</td>
                                <td>{{ rtrim(rtrim(number_format($remaining, 3, ',', '.'), '0'), ',') }}</td>
                                <td>
                                    <label class="visually-hidden" for="purchase-return-quantity-{{ $item->id }}">Jumlah retur {{ $item->material_name }}</label>
                                    <input class="field" id="purchase-return-quantity-{{ $item->id }}" name="items[{{ $index }}][quantity]" type="number" min="0.001" max="{{ $remaining }}" step="0.001" value="{{ old("items.$index.quantity") }}" @disabled($remaining <= 0) placeholder="0">
                                </td>
                                <td>
                                    <label class="visually-hidden" for="replacement-expiry-{{ $item->id }}">Kedaluwarsa item pengganti {{ $item->material_name }}</label>
                                    <input class="field" id="replacement-expiry-{{ $item->id }}" name="items[{{ $index }}][replacement_expires_at]" type="date" min="{{ today()->toDateString() }}" value="{{ old("items.$index.replacement_expires_at") }}">
                                </td>
                                <td>
                                    <label class="visually-hidden" for="purchase-return-reason-{{ $item->id }}">Alasan retur {{ $item->material_name }}</label>
                                    <input class="field" id="purchase-return-reason-{{ $item->id }}" name="items[{{ $index }}][reason]" value="{{ old("items.$index.reason") }}" maxlength="255" placeholder="Alasan retur">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="field-group" style="margin-top:16px">
                <label class="field-label" for="purchase-return-notes">Catatan umum (opsional)</label>
                <textarea class="field" id="purchase-return-notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
            </div>
            <button class="button" type="submit">Simpan permintaan retur pembelian</button>
        </form>
    </section>
@elseif ($filters['purchase_id'])
    <div class="alert alert-error" role="alert">Pembelian tidak ditemukan atau barangnya belum diterima, sehingga tidak dapat diretur.</div>
@endif

<section class="panel" id="riwayat-retur-pembelian">
    <div class="panel-head">
        <div><h2>Riwayat retur pembelian</h2><p>Refund uang tidak mengubah sisa utang; pengurangan utang hanya mengurangi saldo pemasok setelah dikonfirmasi.</p></div>
        <span class="status-tag">{{ $returns->total() }} retur</span>
    </div>
    <form method="GET" action="{{ route('pos.purchase-returns.index') }}" class="filter-row" style="align-items:end">
        <div style="flex:1 1 220px">
            <label class="field-label" for="purchase-return-history-search">Cari pembelian, pemasok, atau kode retur</label>
            <input class="field" id="purchase-return-history-search" name="search" value="{{ $filters['search'] }}">
        </div>
        <div>
            <label class="field-label" for="purchase-return-status">Status</label>
            <select class="field" id="purchase-return-status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua status</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Menunggu konfirmasi</option>
                <option value="confirmed" @selected($filters['status'] === 'confirmed')>Dikonfirmasi</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Dibatalkan</option>
            </select>
        </div>
        <button class="button" type="submit">Terapkan</button>
    </form>
    @if ($returns->isEmpty())
        <div class="empty-state">Belum ada permintaan retur pembelian.</div>
    @else
        @foreach ($returns as $return)
            @php
                $statusLabel = ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Dikonfirmasi', 'cancelled' => 'Dibatalkan'][$return->status] ?? $return->status;
                $methodLabel = [
                    'refund' => 'Refund uang dari pemasok',
                    'replacement' => 'Barang pengganti',
                    'debt_reduction' => 'Pengurangan utang pemasok',
                ][$return->settlement_method] ?? $return->settlement_method;
            @endphp
            <article class="order-card">
                <div class="order-card-head">
                    <div>
                        <h3 class="order-code">{{ $return->return_code }} · {{ $return->purchase->purchase_code }}</h3>
                        <p class="order-meta">{{ $return->return_date->locale('id')->translatedFormat('d M Y') }} · {{ $return->purchase->supplier->name }} · {{ $methodLabel }}</p>
                        <p class="order-meta">Nilai settlement: Rp{{ number_format($return->amount, 0, ',', '.') }}{{ $return->settlement_method === 'replacement' ? ' · non-tunai' : '' }}</p>
                    </div>
                    <span class="status-tag {{ $return->status === 'confirmed' ? 'paid' : ($return->status === 'cancelled' ? 'cancelled' : 'pending') }}">{{ $statusLabel }}</span>
                </div>
                <details class="order-card-details" @if ($errors->any()) open @endif>
                    <summary class="button button-secondary button-small">Detail dan tindakan retur pembelian</summary>
                <ul class="order-lines">
                    @foreach ($return->details as $detail)
                        <li>{{ rtrim(rtrim(number_format((float) $detail->quantity, 3, ',', '.'), '0'), ',') }} {{ $detail->purchaseItem->unit_name }} {{ $detail->purchaseItem->material_name }} · {{ $detail->reason }}
                            @if ($return->settlement_method !== 'replacement') — Rp{{ number_format($detail->amount, 0, ',', '.') }} @endif
                            @if ($return->settlement_method === 'replacement' && $detail->replacement_expires_at) · kedaluwarsa pengganti {{ $detail->replacement_expires_at->locale('id')->translatedFormat('d M Y') }} @endif
                        </li>
                    @endforeach
                    @if ($return->notes)<li>Catatan: {{ $return->notes }}</li>@endif
                </ul>
                @if ($return->status === 'pending')
                    <div class="order-actions">
                        <form method="POST" action="{{ route('pos.purchase-returns.confirm', $return) }}" onsubmit="return confirm('Konfirmasi retur ini? Batch asal akan dikurangi; barang pengganti akan menambah batch baru.')">
                            @csrf @method('PATCH')
                            <button class="button button-small" type="submit">Konfirmasi retur</button>
                        </form>
                        <form method="POST" action="{{ route('pos.purchase-returns.cancel', $return) }}" onsubmit="return confirm('Batalkan permintaan retur pembelian ini?')">
                            @csrf @method('PATCH')
                            <button class="button button-small button-danger" type="submit">Batalkan</button>
                        </form>
                    </div>
                @endif
                </details>
            </article>
        @endforeach
        @if ($returns->hasPages())
            <nav class="pagination" aria-label="Halaman riwayat retur pembelian">
                @if ($returns->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $returns->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $returns->currentPage() }} dari {{ $returns->lastPage() }}</span>
                @if ($returns->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $returns->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>
@endsection
