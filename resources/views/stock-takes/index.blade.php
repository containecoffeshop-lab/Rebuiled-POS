@extends('layouts.app')

@section('title', 'Stok opname')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Persediaan</p><h1>Stok opname</h1><p>Bandingkan hitungan fisik dengan stok bahan yang masih aktif. Batch kedaluwarsa tidak termasuk stok tersedia.</p></div>
    <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Catat hasil hitung</h2><p>Hanya bahan yang dicentang akan disimpan. Selisih langsung masuk ke kartu stok dan opname tidak dapat diedit setelah disimpan.</p></div></div>
    @if ($materials->isEmpty())
        <div class="empty-state">Belum ada bahan untuk dihitung.</div>
    @else
        <form class="pos-order-form" method="POST" action="{{ route('pos.stock-takes.store') }}" data-stock-take-form onsubmit="return confirm('Simpan hasil opname dan sesuaikan mutasi stok sekarang?')">
            @csrf
            <div class="form-grid">
                <div class="field-group">
                    <label class="field-label" for="stock-take-reference">Nomor referensi</label>
                    <input class="field" id="stock-take-reference" name="reference" value="{{ old('reference', 'SO-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(5))) }}" maxlength="50" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="stock-take-date">Tanggal hitung</label>
                    <input class="field" id="stock-take-date" name="checked_at" type="date" value="{{ old('checked_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="stock-take-note">Catatan umum (opsional)</label>
                    <input class="field" id="stock-take-note" name="note" maxlength="1000">
                </div>
            </div>
            <div class="table-wrap">
                <table class="menu-table">
                    <thead><tr><th>Pilih</th><th>Bahan</th><th>Stok tersedia sistem</th><th>Jumlah fisik</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($materials as $index => $material)
                            <tr data-stock-take-row>
                                <td><input type="checkbox" data-stock-take-toggle @checked((int) old("items.$index.material_id") === $material->id) aria-label="Hitung {{ $material->name }}"></td>
                                <td><strong>{{ $material->name }}</strong><br><span class="order-meta">{{ $material->unit->name }}</span></td>
                                <td>{{ number_format($material->stock_on_hand, 3, ',', '.') }} {{ $material->unit->name }}</td>
                                <td>
                                    <input type="hidden" name="items[{{ $index }}][material_id]" value="{{ $material->id }}" data-stock-take-value disabled>
                                    <input class="field" name="items[{{ $index }}][counted_quantity]" type="number" min="0" max="999999999.999" step="0.001" value="{{ old("items.$index.counted_quantity", number_format($material->stock_on_hand, 3, '.', '')) }}" data-stock-take-value disabled aria-label="Jumlah fisik {{ $material->name }}">
                                </td>
                                <td><input class="field" name="items[{{ $index }}][note]" value="{{ old("items.$index.note") }}" maxlength="500" data-stock-take-value disabled aria-label="Catatan {{ $material->name }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button class="button" type="submit" style="margin-top:16px">Simpan & sesuaikan stok</button>
        </form>
    @endif
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Riwayat opname</h2><p>Setiap penyesuaian disimpan bersama petugas dan nilai stok sebelum/sesudah.</p></div></div>
    @if ($stockTakes->isEmpty())
        <div class="empty-state">Belum ada riwayat stok opname.</div>
    @else
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Referensi</th><th>Tanggal</th><th>Petugas</th><th>Bahan dihitung</th><th>Bahan berbeda</th><th>Rincian</th></tr></thead>
                <tbody>
                    @foreach ($stockTakes as $stockTake)
                        <tr>
                            <td><strong>{{ $stockTake->reference }}</strong></td>
                            <td>{{ $stockTake->checked_at->locale('id')->translatedFormat('d M Y') }}</td>
                            <td>{{ $stockTake->user->name }}</td>
                            <td>{{ $stockTake->details->count() }}</td>
                            <td>{{ $stockTake->details->filter(fn ($detail) => (float) $detail->difference !== 0.0)->count() }}</td>
                            <td>
                                <details>
                                    <summary class="nav-link" style="cursor:pointer">Lihat</summary>
                                    <ul class="order-lines">
                                        @foreach ($stockTake->details as $detail)
                                            <li>{{ $detail->material_name }}: sistem {{ number_format((float) $detail->system_quantity, 3, ',', '.') }}, fisik {{ number_format((float) $detail->counted_quantity, 3, ',', '.') }} {{ $detail->unit_name }} ({{ (float) $detail->difference > 0 ? '+' : '' }}{{ number_format((float) $detail->difference, 3, ',', '.') }}){{ $detail->note ? ' · '.$detail->note : '' }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px">{{ $stockTakes->links() }}</div>
    @endif
</section>
@endsection
