@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Analisis usaha</p>
        <h1>Laporan operasional.</h1>
        <p>Ringkasan penjualan, pembelian, arus kas, dan mutasi bahan berdasarkan tanggal.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
        <a class="button button-secondary" href="{{ route('pos.purchases.index') }}">Pembelian</a>
        <a class="button button-secondary" href="{{ route('pos.expenses.index') }}">Pengeluaran</a>
    </div>
</div>

<section class="panel">
    <form method="GET" action="{{ route('pos.reports.index') }}" class="filter-row" style="align-items:end">
        <div>
            <label class="field-label" for="report-type">Jenis laporan</label>
            <select class="field" id="report-type" name="report">
                <option value="sales" @selected($filters['report'] === 'sales')>Penjualan</option>
                <option value="purchases" @selected($filters['report'] === 'purchases')>Pembelian</option>
                <option value="cash-flow" @selected($filters['report'] === 'cash-flow')>Arus kas</option>
                <option value="stock" @selected($filters['report'] === 'stock')>Stok bahan</option>
                <option value="stock-card" @selected($filters['report'] === 'stock-card')>Kartu stok bahan</option>
                <option value="product-sales" @selected($filters['report'] === 'product-sales')>Produk terjual</option>
                <option value="menu-margin" @selected($filters['report'] === 'menu-margin')>Margin per menu</option>
                <option value="profit-loss" @selected($filters['report'] === 'profit-loss')>Laba kotor bahan</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="report-start">Dari tanggal</label>
            <input class="field" id="report-start" name="start_date" type="date" value="{{ $filters['start_date'] }}" required>
        </div>
        <div>
            <label class="field-label" for="report-end">Sampai tanggal</label>
            <input class="field" id="report-end" name="end_date" type="date" value="{{ $filters['end_date'] }}" required>
        </div>
        @if (in_array($filters['report'], ['sales', 'purchases', 'cash-flow', 'stock', 'product-sales', 'menu-margin', 'profit-loss'], true))
            <div style="flex:1 1 200px">
                <label class="field-label" for="report-search">Pencarian</label>
                <input class="field" id="report-search" name="search" value="{{ $filters['search'] }}" placeholder="Invoice, pelanggan, pemasok, bahan">
            </div>
        @endif
        @if ($filters['report'] === 'stock-card')
            <div style="flex:1 1 220px">
                <label class="field-label" for="report-material">Bahan</label>
                <select class="field" id="report-material" name="material_id">
                    <option value="">Pilih bahan untuk melihat kartu stok</option>
                    @foreach ($materials as $material)
                        <option value="{{ $material->id }}" @selected((string) $filters['material_id'] === (string) $material->id)>{{ $material->name }} · {{ $material->unit?->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($filters['report'] === 'stock')
            <label class="filter-chip" style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="hide_zero" value="1" @checked($filters['hide_zero'])>
                Sembunyikan bahan tanpa stok tersedia
            </label>
        @endif
        <button class="button" type="submit">Terapkan</button>
        <a class="button button-secondary" href="{{ route('pos.reports.index', ['report' => $filters['report']]) }}">Reset</a>
    </form>
</section>

@if ($reportData['summary'])
    <section class="stats-grid" aria-label="Ringkasan {{ $reportData['title'] }}">
        @foreach ($reportData['summary'] as $item)
            <article class="stat-card">
                <span class="stat-label">{{ $item['label'] }}</span>
                <strong class="stat-value">{{ $item['value'] }}</strong>
            </article>
        @endforeach
    </section>
@endif

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>{{ $reportData['title'] }}</h2>
            <p>Periode {{ \Illuminate\Support\Carbon::parse($filters['start_date'])->locale('id')->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($filters['end_date'])->locale('id')->translatedFormat('d M Y') }}</p>
        </div>
        <a class="button button-secondary" href="{{ route('pos.reports.export', $filters) }}">Unduh CSV</a>
    </div>

    @if ($reportData['kind'] === 'profit-loss')
        <div class="alert" role="note">Laba kotor memakai harga jual setelah diskon dan HPP batch yang tersnapshot saat bahan dipakai. PJK dikecualikan dari pendapatan usaha; OPR dihitung sebagai pendapatan biaya layanan. Baris menampilkan pendapatan transaksi sebelum retur; refund terkonfirmasi dikurangkan pada ringkasan berdasarkan tanggal retur dengan alokasi proporsional di luar PJK. Baris tanpa biaya bahan yang diketahui atau tanpa resep lengkap ditandai; laba total ditahan bila ada transaksi seperti itu. Biaya operasional belum dikurangkan.</div>
    @endif
    @if ($reportData['kind'] === 'menu-margin')
        <div class="alert" role="note">Ini estimasi margin menu: biaya bahan batch saat transaksi dialokasikan ke item resep berdasarkan rata-rata biaya bahan per transaksi. Diskon kupon dan loyalitas dibagi proporsional; biaya layanan, produk eceran, dan refund tidak termasuk. Baris tanpa resep atau biaya bahan lengkap ditandai dan laba total tidak ditampilkan bila ada data yang kurang.</div>
    @endif

    @if ($reportData['kind'] === 'sales')
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada transaksi lunas pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Tanggal</th><th>Invoice</th><th>Pelanggan</th><th>Jenis</th><th>Kasir</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $sale)
                            <tr>
                                <td>{{ $sale->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                                <td><strong>{{ $sale->invoice }}</strong></td>
                                <td>{{ $sale->customer_name }}</td>
                                <td>{{ ['dine_in' => 'Makan di tempat', 'takeaway' => 'Bawa pulang'][$sale->transaction_type] ?? $sale->transaction_type }}</td>
                                <td>{{ $sale->cashierShift?->user?->name ?? '—' }}</td>
                                <td>Rp{{ number_format($sale->grand_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($reportData['kind'] === 'purchases')
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada pembelian pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Tanggal</th><th>Pembelian</th><th>Pemasok</th><th>Status barang</th><th>Status bayar</th><th>Total</th><th>Dibayar</th><th>Saldo</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $purchase)
                            @php
                                $paid = (int) $purchase->payments->sum('amount');
                                $credit = (int) $purchase->returns->where('settlement_method', 'debt_reduction')->where('status', 'confirmed')->sum('amount');
                                $reserved = (int) $purchase->returns->where('settlement_method', 'debt_reduction')->where('status', 'pending')->sum('amount');
                                $remaining = max(0, $purchase->grand_total - $paid - $credit - $reserved);
                            @endphp
                            <tr>
                                <td>{{ $purchase->purchase_date->locale('id')->translatedFormat('d M Y') }}</td>
                                <td><strong>{{ $purchase->purchase_code }}</strong></td>
                                <td>{{ $purchase->supplier->name }}</td>
                                <td>{{ ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Menunggu barang', 'received' => 'Diterima'][$purchase->status] ?? $purchase->status }}</td>
                                <td>{{ ['unpaid' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas'][$purchase->payment_status] ?? $purchase->payment_status }}</td>
                                <td>Rp{{ number_format($purchase->grand_total, 0, ',', '.') }}</td>
                                <td>Rp{{ number_format($paid, 0, ',', '.') }}</td>
                                <td>Rp{{ number_format($remaining, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($reportData['kind'] === 'cash-flow')
        <div class="alert" role="note">Ini ringkasan arus pembayaran aktual, bukan perubahan kas shift. Pengurangan utang dan klaim penggantian non-tunai tidak dihitung sebagai arus kas.</div>
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada pembayaran atau refund tunai pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Tanggal</th><th>Referensi</th><th>Arus</th><th>Keterangan</th><th>Metode</th><th>Rekening</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->flow_date)->locale('id')->translatedFormat('d M Y') }}</td>
                                <td><strong>{{ $row->reference }}</strong></td>
                                <td>{{ $row->direction }} · {{ $row->entry_type }}</td>
                                <td>{{ $row->description ?: '—' }}</td>
                                <td>{{ $row->payment_method ?: '—' }}</td>
                                <td>{{ $row->bank_account ?: '—' }}</td>
                                <td>{{ $row->direction === 'Masuk' ? '+' : '−' }}Rp{{ number_format($row->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($reportData['kind'] === 'stock')
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada bahan sesuai filter.</div>
        @else
            @foreach ($reportData['rows'] as $row)
                <article class="order-card">
                    <div class="order-card-head">
                        <div>
                            <h3 class="order-code">{{ $row['material'] }}</h3>
                            <p class="order-meta">Batas minimum {{ number_format($row['minimum'], 3, ',', '.') }} {{ $row['unit'] }} · Stok tersedia {{ number_format($row['current'], 3, ',', '.') }} {{ $row['unit'] }} · Kedaluwarsa {{ number_format($row['expired'], 3, ',', '.') }} {{ $row['unit'] }}</p>
                            <p class="order-meta">Stok awal {{ number_format($row['opening'], 3, ',', '.') }} · Masuk {{ number_format($row['in'], 3, ',', '.') }} · Keluar {{ number_format($row['out'], 3, ',', '.') }} · Saldo semua batch {{ number_format($row['closing'], 3, ',', '.') }} {{ $row['unit'] }}</p>
                        </div>
                        <span class="status-tag {{ $row['current'] < $row['minimum'] ? 'pending' : 'paid' }}">{{ $row['current'] < $row['minimum'] ? 'Di bawah minimum' : 'Tersedia' }}</span>
                    </div>
                    @if ($row['batches']->isNotEmpty())
                        <div class="table-wrap">
                            <table class="menu-table">
                                <thead><tr><th>Batch</th><th>Kedaluwarsa</th><th>Status</th><th>Awal</th><th>Masuk</th><th>Keluar</th><th>Saldo</th></tr></thead>
                                <tbody>
                                    @foreach ($row['batches'] as $batch)
                                        <tr>
                                            <td>{{ $batch['batch'] }}</td>
                                            <td>{{ $batch['expires_at'] ?: '—' }}</td>
                                            <td>{{ $batch['expired'] ? 'Kedaluwarsa' : ($batch['closing'] <= 0 ? 'Habis' : 'Aktif') }}</td>
                                            <td>{{ number_format($batch['opening'], 3, ',', '.') }}</td>
                                            <td>{{ number_format($batch['in'], 3, ',', '.') }}</td>
                                            <td>{{ number_format($batch['out'], 3, ',', '.') }}</td>
                                            <td>{{ number_format($batch['closing'], 3, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach
        @endif
    @elseif ($reportData['kind'] === 'stock-card')
        @if (! $reportData['material'])
            <div class="empty-state">Pilih bahan di atas untuk melihat saldo awal, pergerakan, dan saldo berjalan.</div>
        @elseif ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada mutasi bahan ini pada periode terpilih.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Tanggal</th><th>Batch</th><th>Jenis</th><th>Jumlah</th><th>Saldo berjalan</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $row)
                            <tr>
                                <td>{{ $row['date']->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                                <td>{{ $row['batch'] }}</td>
                                <td>{{ $row['type'] === 'in' ? 'Masuk' : 'Keluar' }}</td>
                                <td>{{ $row['type'] === 'in' ? '+' : '−' }}{{ number_format($row['quantity'], 3, ',', '.') }} {{ $reportData['material']->unit?->name }}</td>
                                <td>{{ number_format($row['balance'], 3, ',', '.') }} {{ $reportData['material']->unit?->name }}</td>
                                <td>{{ $row['description'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($reportData['kind'] === 'menu-margin')
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada menu terjual lunas pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Menu / varian</th><th>Jumlah terjual</th><th>Penjualan setelah diskon</th><th>Estimasi HPP bahan</th><th>Estimasi laba kotor</th><th>Margin</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row['item_name'] }}</strong></td>
                                <td>{{ number_format($row['quantity'], 0, ',', '.') }}</td>
                                <td>Rp{{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td>{{ $row['cogs'] === null ? 'Belum diketahui' : 'Rp'.number_format($row['cogs'], 0, ',', '.') }}</td>
                                <td>{{ $row['gross_profit'] === null ? '—' : 'Rp'.number_format($row['gross_profit'], 0, ',', '.') }}</td>
                                <td>{{ $row['margin_percent'] === null ? '—' : number_format($row['margin_percent'], 1, ',', '.').'%' }}</td>
                                <td><span class="status-tag {{ $row['complete'] ? 'paid' : 'pending' }}">{{ $row['complete'] ? 'Lengkap' : 'Belum lengkap' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($reportData['kind'] === 'profit-loss')
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Tidak ada transaksi lunas pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Tanggal bayar</th><th>Invoice</th><th>Pelanggan</th><th>Pendapatan di luar PJK</th><th>HPP saat pemakaian</th><th>Laba kotor sebelum refund</th><th>Status HPP</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $row)
                            <tr>
                                <td>{{ $row->paid_at?->locale('id')->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                                <td><strong>{{ $row->invoice }}</strong></td>
                                <td>{{ $row->customer_name ?: '—' }}</td>
                                <td>Rp{{ number_format($row->recognized_revenue, 0, ',', '.') }}</td>
                                <td>{{ $row->cogs_complete ? 'Rp'.number_format($row->cogs_total, 0, ',', '.') : 'Belum diketahui' }}</td>
                                <td>{{ $row->cogs_complete ? 'Rp'.number_format($row->recognized_revenue - $row->cogs_total, 0, ',', '.') : '—' }}</td>
                                <td><span class="status-tag {{ $row->cogs_complete ? 'paid' : 'pending' }}">{{ $row->cogs_complete ? 'Lengkap' : 'Belum lengkap' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        @if ($reportData['rows']->isEmpty())
            <div class="empty-state">Belum ada item terjual pada periode ini.</div>
        @else
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Menu</th><th>Harga snapshot</th><th>Jumlah terjual</th><th>Nilai kotor item</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['rows'] as $row)
                            <tr>
                                <td><strong>{{ $row->item_name }}</strong></td>
                                <td>Rp{{ number_format($row->price, 0, ',', '.') }}</td>
                                <td>{{ number_format($row->quantity, 0, ',', '.') }}</td>
                                <td>Rp{{ number_format($row->revenue, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if ($reportData['rows']->hasPages())
        <nav class="pagination" aria-label="Halaman laporan">
            @if ($reportData['rows']->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $reportData['rows']->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
            <span>Halaman {{ $reportData['rows']->currentPage() }} dari {{ $reportData['rows']->lastPage() }}</span>
            @if ($reportData['rows']->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $reportData['rows']->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
        </nav>
    @endif
</section>

@endsection
