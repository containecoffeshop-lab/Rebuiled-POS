@extends('layouts.app')

@section('title', 'Retur penjualan')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Layanan pelanggan</p>
        <h1>Retur penjualan.</h1>
        <p>Catat pengembalian uang atau klaim penggantian untuk transaksi yang sudah lunas.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Cari transaksi lunas</h2><p>Jumlah yang masih bisa diretur dihitung dari item dan retur aktif sebelumnya.</p></div>
    </div>
    <form method="GET" action="{{ route('pos.returns.index') }}" class="filter-row">
        <div style="flex:1 1 260px">
            <label class="field-label" for="return-search">Invoice, nama, atau telepon pelanggan</label>
            <input class="field" id="return-search" name="search" value="{{ $filters['search'] }}" placeholder="Cari transaksi lunas">
        </div>
        <button class="button" type="submit">Cari transaksi</button>
        <a class="button button-secondary" href="{{ route('pos.returns.index') }}">Reset</a>
    </form>

    @if ($eligibleTransactions->isEmpty())
        <div class="empty-state">Tidak ada transaksi lunas yang cocok. Cari berdasarkan nomor invoice atau pelanggan.</div>
    @else
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Tanggal</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @foreach ($eligibleTransactions as $sale)
                        <tr>
                            <td><strong>{{ $sale->invoice }}</strong></td>
                            <td>{{ $sale->customer_name }}{{ $sale->customer_phone ? ' · '.$sale->customer_phone : '' }}</td>
                            <td>{{ $sale->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                            <td>Rp{{ number_format($sale->grand_total, 0, ',', '.') }}</td>
                            <td><a class="button button-secondary button-small" href="{{ route('pos.returns.index', array_filter(['search' => $filters['search'], 'status' => $filters['status'], 'transaction_id' => $sale->id])) }}">Pilih transaksi</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($eligibleTransactions->hasPages())
            <nav class="pagination" aria-label="Halaman transaksi lunas">
                @if ($eligibleTransactions->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $eligibleTransactions->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $eligibleTransactions->currentPage() }} dari {{ $eligibleTransactions->lastPage() }}</span>
                @if ($eligibleTransactions->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $eligibleTransactions->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>

@if ($selectedTransaction)
    <section class="panel" id="buat-retur">
        <div class="panel-head">
            <div>
                <h2>Ajukan retur · {{ $selectedTransaction->invoice }}</h2>
                <p>{{ $selectedTransaction->customer_name }} · Total transaksi Rp{{ number_format($selectedTransaction->grand_total, 0, ',', '.') }}. Maksimum refund mengikuti nilai bersih setelah diskon.</p>
            </div>
            <span class="status-tag paid">Lunas</span>
        </div>
        <form method="POST" action="{{ route('pos.returns.store') }}">
            @csrf
            <input type="hidden" name="transaction_id" value="{{ $selectedTransaction->id }}">
            <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="field-group">
                    <label class="field-label" for="return-date">Tanggal retur</label>
                    <input class="field" id="return-date" name="return_date" type="date" value="{{ old('return_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="return-method">Penyelesaian</label>
                    <select class="field" id="return-method" name="refund_method" required>
                        <option value="refund" @selected(old('refund_method', 'refund') === 'refund')>Refund uang</option>
                        <option value="replacement" @selected(old('refund_method') === 'replacement')>Klaim penggantian non-tunai</option>
                    </select>
                </div>
            </div>
            <div class="alert" role="note">Klaim penggantian hanya dicatat sebagai penyelesaian non-tunai; aplikasi tidak membuat order baru, tidak mengirim ke dapur, dan tidak menambah stok bahan.</div>
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Item transaksi</th><th>Terjual</th><th>Sudah diminta / dikonfirmasi</th><th>Sisa dapat diretur</th><th>Jumlah retur</th><th>Alasan</th></tr></thead>
                    <tbody>
                        @foreach ($selectedTransaction->details as $index => $detail)
                            @php
                                $reserved = $detail->returnDetails
                                    ->filter(fn ($row) => in_array($row->transactionReturn?->status, ['pending', 'confirmed'], true))
                                    ->sum('quantity');
                                $remaining = max(0, $detail->quantity - $reserved);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $detail->item_name }}</strong>
                                    <div class="order-meta">Rp{{ number_format($detail->price, 0, ',', '.') }} / item</div>
                                    <input type="hidden" name="items[{{ $index }}][transaction_detail_id]" value="{{ $detail->id }}">
                                </td>
                                <td>{{ $detail->quantity }}</td>
                                <td>{{ $reserved }}</td>
                                <td>{{ $remaining }}</td>
                                <td>
                                    <label class="visually-hidden" for="return-quantity-{{ $detail->id }}">Jumlah retur {{ $detail->item_name }}</label>
                                    <select class="field" id="return-quantity-{{ $detail->id }}" name="items[{{ $index }}][quantity]" @disabled($remaining === 0)>
                                        <option value="">Tidak diretur</option>
                                        @for ($quantity = 1; $quantity <= $remaining; $quantity++)
                                            <option value="{{ $quantity }}" @selected((string) old("items.$index.quantity") === (string) $quantity)>{{ $quantity }}</option>
                                        @endfor
                                    </select>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="return-reason-{{ $detail->id }}">Alasan retur {{ $detail->item_name }}</label>
                                    <input class="field" id="return-reason-{{ $detail->id }}" name="items[{{ $index }}][reason]" value="{{ old("items.$index.reason") }}" maxlength="255" placeholder="Alasan retur">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="field-group" style="margin-top:16px">
                <label class="field-label" for="return-notes">Catatan umum (opsional)</label>
                <textarea class="field" id="return-notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
            </div>
            <button class="button" type="submit">Simpan permintaan retur</button>
        </form>
    </section>
@elseif ($filters['transaction_id'])
    <div class="alert alert-error" role="alert">Transaksi tidak ditemukan atau belum lunas, sehingga tidak dapat diretur.</div>
@endif

<section class="panel" id="riwayat-retur">
    <div class="panel-head">
        <div><h2>Riwayat retur</h2><p>Refund dihitung sebagai pengembalian uang; klaim penggantian tidak mengubah kas, penjualan, dapur, atau stok.</p></div>
        <span class="status-tag">{{ $returns->total() }} retur</span>
    </div>
    <form method="GET" action="{{ route('pos.returns.index') }}" class="filter-row" style="align-items:end">
        <div style="flex:1 1 220px">
            <label class="field-label" for="return-history-search">Cari invoice atau kode retur</label>
            <input class="field" id="return-history-search" name="search" value="{{ $filters['search'] }}">
        </div>
        <div>
            <label class="field-label" for="return-status">Status</label>
            <select class="field" id="return-status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua status</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Menunggu konfirmasi</option>
                <option value="confirmed" @selected($filters['status'] === 'confirmed')>Dikonfirmasi</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Dibatalkan</option>
            </select>
        </div>
        <button class="button" type="submit">Terapkan</button>
    </form>
    @if ($returns->isEmpty())
        <div class="empty-state">Belum ada permintaan retur.</div>
    @else
        @foreach ($returns as $return)
            @php
                $statusLabel = ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Dikonfirmasi', 'cancelled' => 'Dibatalkan'][$return->status] ?? $return->status;
                $methodLabel = $return->refund_method === 'refund' ? 'Refund uang' : 'Klaim penggantian non-tunai';
            @endphp
            <article class="order-card">
                <div class="order-card-head">
                    <div>
                        <h3 class="order-code">{{ $return->return_code }} · {{ $return->transaction->invoice }}</h3>
                        <p class="order-meta">{{ $return->return_date->locale('id')->translatedFormat('d M Y') }} · {{ $return->transaction->customer_name }} · {{ $methodLabel }}</p>
                        <p class="order-meta">{{ $return->refund_method === 'refund' ? 'Nilai refund' : 'Nilai kas keluar' }}: Rp{{ number_format($return->refund_amount, 0, ',', '.') }}</p>
                    </div>
                    <span class="status-tag {{ $return->status === 'confirmed' ? 'paid' : ($return->status === 'cancelled' ? 'cancelled' : 'pending') }}">{{ $statusLabel }}</span>
                </div>
                <ul class="order-lines">
                    @foreach ($return->details as $detail)
                        <li>{{ $detail->quantity }} × {{ $detail->transactionDetail->item_name }} · {{ $detail->reason }}
                            @if ($return->refund_method === 'refund') — Rp{{ number_format($detail->refund_amount, 0, ',', '.') }} @endif
                        </li>
                    @endforeach
                    @foreach ($return->pointEntries as $entry)
                        <li>Koreksi poin loyalitas: {{ number_format(abs($entry->points), 0, ',', '.') }} poin dikurangi</li>
                    @endforeach
                    @if ($return->notes)<li>Catatan: {{ $return->notes }}</li>@endif
                </ul>
                @if ($return->status === 'pending')
                    <div class="order-actions">
                        <form method="POST" action="{{ route('pos.returns.confirm', $return) }}">
                            @csrf @method('PATCH')
                            <button class="button button-small" type="submit">Konfirmasi retur</button>
                        </form>
                        <form method="POST" action="{{ route('pos.returns.cancel', $return) }}" onsubmit="return confirm('Batalkan permintaan retur ini?')">
                            @csrf @method('PATCH')
                            <button class="button button-small button-danger" type="submit">Batalkan</button>
                        </form>
                    </div>
                @endif
            </article>
        @endforeach
        @if ($returns->hasPages())
            <nav class="pagination" aria-label="Halaman retur">
                @if ($returns->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $returns->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $returns->currentPage() }} dari {{ $returns->lastPage() }}</span>
                @if ($returns->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $returns->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>
@endsection
