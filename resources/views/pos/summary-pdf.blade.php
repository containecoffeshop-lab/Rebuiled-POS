<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ringkasan {{ $month_label }} · POS</title>
    <style>
        :root { font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', Roboto, sans-serif; color: #23332a; font-size: 11px; }
        body { margin: 24px auto; max-width: 1100px; padding: 0 20px; }
        h1 { margin: 0; color: #355e49; font-size: 24px; }
        h2 { margin: 24px 0 8px; color: #355e49; font-size: 15px; }
        p { color: #637168; }
        .actions { display: flex; gap: 8px; margin-bottom: 20px; }
        button { border: 0; border-radius: 6px; background: #527a68; color: white; padding: 9px 14px; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 18px 0; }
        .metric { padding: 12px; border: 1px solid #dfe5dc; border-radius: 8px; }
        .metric span { display: block; color: #637168; margin-bottom: 6px; }
        .metric strong { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 7px 8px; border: 1px solid #d9e0d7; text-align: left; vertical-align: top; }
        th { background: #e8eee6; }
        .number { text-align: right; white-space: nowrap; }
        @media print {
            body { max-width: none; margin: 0; padding: 0; font-size: 9px; }
            .actions { display: none; }
            .grid { grid-template-columns: repeat(4, 1fr); gap: 5px; }
            .metric { padding: 7px; break-inside: avoid; }
            h2 { break-after: avoid; margin-top: 16px; }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>
    <h1>Ringkasan Penjualan &amp; Operasional</h1>
    <p>{{ $month_label }} · {{ $days_in_month }} hari kalender</p>
    <p>Periode ringkasan aktif: {{ $summary_start ? 'sejak '.$summary_start->format('d/m/Y H:i:s') : 'seluruh riwayat' }}. Riwayat sebelumnya tetap tersedia pada laporan.</p>
    <div class="grid">
        @foreach ($metrics as $label => $value)
            <div class="metric">
                <span>{{ $label }}</span>
                <strong>{{ is_numeric($value) && ! in_array($label, ['Jumlah hari kalender'], true) ? 'Rp'.number_format((int) $value, 0, ',', '.') : $value }}</strong>
            </div>
        @endforeach
    </div>

    <h2>Rincian transaksi lunas</h2>
    <table>
        <thead><tr><th>Tanggal bayar</th><th>Invoice</th><th>Pelanggan</th><th>Menu</th><th class="number">Qty</th><th class="number">Subtotal item</th><th class="number">Diskon</th><th class="number">Total transaksi</th></tr></thead>
        <tbody>
            @forelse ($sales as $transaction)
                @foreach ($transaction->details as $detail)
                    <tr>
                        <td>{{ $transaction->paid_at?->format('d/m/Y H:i') }}</td><td>{{ $transaction->invoice }}</td><td>{{ $transaction->customer_name }}</td><td>{{ $detail->item_name }}</td><td class="number">{{ $detail->quantity }}</td><td class="number">Rp{{ number_format($detail->line_total, 0, ',', '.') }}</td><td class="number">Rp{{ number_format($detail->promotion_discount, 0, ',', '.') }}</td><td class="number">Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="8">Tidak ada transaksi lunas pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pengeluaran operasional</h2>
    <table>
        <thead><tr><th>Tanggal</th><th>Kode</th><th>Kategori</th><th>Keterangan</th><th>Status</th><th class="number">Nominal</th></tr></thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr><td>{{ $expense->expense_date }}</td><td>{{ $expense->expense_code }}</td><td>{{ $expense->category_name }}</td><td>{{ $expense->description ?: '—' }}</td><td>{{ $expense->is_non_cash ? 'Non-tunai (konsumsi)' : $expense->payment_status }}</td><td class="number">Rp{{ number_format($expense->amount, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="6">Tidak ada pengeluaran pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pemasukan, pembayaran pengeluaran & pencapaian target per hari</h2>
    <table>
        <thead><tr><th>Tanggal</th><th class="number">Pemasukan</th><th class="number">Pengeluaran dibayar</th><th class="number">Target omzet</th><th class="number">Pencapaian</th></tr></thead>
        <tbody>
            @foreach ($daily as $day)
                <tr><td>{{ $day['date'] }}</td><td class="number">Rp{{ number_format($day['income'], 0, ',', '.') }}</td><td class="number">Rp{{ number_format($day['expenses_paid'], 0, ',', '.') }}</td><td class="number">{{ $day['target'] === null ? 'HPP belum lengkap' : 'Rp'.number_format($day['target'], 0, ',', '.') }}</td><td class="number">{{ $day['achievement'] === null ? '—' : number_format($day['achievement'], 1, ',', '.').'%' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
