@extends('layouts.app')

@section('title', 'Ringkasan tutup kasir')

@section('content')
<div class="shift-report-page">
    <p class="no-print">Shift telah ditutup. <a href="{{ route('pos.shift.report', $shift) }}">Buka kembali ringkasan</a> atau <a href="{{ route('pos.index', ['module' => 'kasir']) }}">kembali ke kasir</a>.</p>
    <dialog class="details-dialog shift-report-dialog" open aria-labelledby="shift-report-title" data-shift-report-dialog>
        <div class="details-dialog-head no-print">
            <h2 id="shift-report-title">Ringkasan tutup kasir</h2>
            <a class="button button-secondary button-small" href="{{ route('pos.index', ['module' => 'kasir']) }}">Tutup</a>
        </div>
        <article class="receipt-card shift-report-content">
            <header class="receipt-header">
                <h1>{{ $storeSettings['name'] ?? 'Rasa' }}</h1>
                <p>Ringkasan tutup kasir</p>
            </header>
            <div class="receipt-meta">
                <div><span>Kasir</span><strong>{{ $shift->user->name }}</strong></div>
                <div><span>Shift</span><strong>{{ $shift->shift_number ? 'Shift '.$shift->shift_number : 'Tanpa nomor' }}</strong></div>
                <div><span>Dibuka</span><strong>{{ $shift->opened_at->locale('id')->translatedFormat('d M Y, H:i') }}</strong></div>
                <div><span>Ditutup</span><strong>{{ $shift->closed_at->locale('id')->translatedFormat('d M Y, H:i') }}</strong></div>
                <div><span>Tanggal laporan</span><strong>{{ $shift->closed_at->locale('id')->translatedFormat('d M Y') }}</strong></div>
            </div>
            <div class="receipt-items">
                <strong>Transaksi lunas ({{ $transactions->count() }})</strong>
                @forelse ($transactions as $transaction)
                    <div class="receipt-item">
                        <div><strong>{{ $transaction->invoice }}</strong><span>{{ $transaction->paid_at->format('H:i') }} · {{ $transaction->payment_method === 'cash' ? 'Tunai' : ($transaction->payment_method === 'transfer' ? 'Transfer' : $transaction->payment_method) }}</span></div>
                        <strong>Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <p class="receipt-note">Tidak ada transaksi lunas pada tanggal penutupan.</p>
                @endforelse
            </div>
            <div class="receipt-totals">
                <div><span>Penjualan tunai</span><strong>Rp{{ number_format($cashSales, 0, ',', '.') }}</strong></div>
                <div><span>Penjualan non-tunai (transfer)</span><strong>Rp{{ number_format($transferSales, 0, ',', '.') }}</strong></div>
                <div><span>Total penjualan</span><strong>Rp{{ number_format($totalSales, 0, ',', '.') }}</strong></div>
                <div><span>Modal kas awal</span><strong>Rp{{ number_format($shift->starting_cash, 0, ',', '.') }}</strong></div>
                <div class="receipt-grand-total"><span>Penjualan dikurangi modal kas</span><strong>Rp{{ number_format($afterStartingCash, 0, ',', '.') }}</strong></div>
                <div><span>Kas fisik saat tutup</span><strong>Rp{{ number_format($shift->ending_cash, 0, ',', '.') }}</strong></div>
                <div><span>Kas diharapkan (seluruh shift)</span><strong>Rp{{ number_format($shift->expected_cash, 0, ',', '.') }}</strong></div>
                <div><span>Selisih kas (seluruh shift)</span><strong>Rp{{ number_format($shift->cash_difference, 0, ',', '.') }}</strong></div>
            </div>
            @if ($shift->closing_note)<p class="receipt-note">Catatan selisih: {{ $shift->closing_note }}</p>@endif
            <p class="receipt-note">Hanya penjualan lunas dalam shift ini pada tanggal penutupan. Pesanan online mengikuti shift penerima, bukan akun yang memproses pembayaran. Retur dan pengeluaran tidak dikurangi. Angka penjualan dikurangi modal bukan laba atau saldo kas.</p>
        </article>
        <div class="shift-report-actions no-print">
            <button class="button" type="button" onclick="window.print()">Cetak ringkasan</button>
            <a class="button button-secondary" href="{{ route('pos.index', ['module' => 'kasir']) }}">Kembali ke kasir</a>
        </div>
    </dialog>
</div>
@endsection

@push('scripts')
    <script>
        const shiftReportDialog = document.querySelector('[data-shift-report-dialog]');
        if (shiftReportDialog && typeof shiftReportDialog.showModal === 'function') {
            shiftReportDialog.removeAttribute('open');
            shiftReportDialog.showModal();
        }
    </script>
@endpush
