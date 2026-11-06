@extends('layouts.app')

@section('title', 'Audit Stock')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Audit persediaan</p><h1>Audit Stock</h1><p>Hitung fisik dua kali secara berurutan. Kedua sesi dicatat terpisah; audit tidak mengubah stok otomatis.</p></div>
    <a class="button button-secondary" href="{{ route('pos.audit.index') }}">Kembali ke audit</a>
</div>

@if ($pendingTask)
    <section class="panel">
        <div class="panel-head"><div><h2>Shift 2 · Tugas {{ $pendingTask->task_code }}</h2><p>Shift 1 diselesaikan {{ $pendingTask->shift1_completed_at->locale('id')->translatedFormat('d M Y, H:i') }} oleh {{ $pendingTask->shift1User->name }}. Lakukan hitung independen tanpa melihat angka Shift 1.</p></div><span class="status-tag pending">Menunggu Shift 2</span></div>
        @if (auth()->id() === $pendingTask->shift1_user_id)
            <div class="alert">Gunakan akun karyawan lain untuk Shift 2 agar hitungan tetap independen.</div>
        @else
            <form method="POST" action="{{ route('pos.stock-audits.shift2', $pendingTask) }}" class="pos-order-form">
                @csrf
                <div class="field-group" style="max-width:320px;margin-bottom:16px">
                    <label class="field-label" for="shift2-task-code">Konfirmasi kode TASK</label>
                    <input class="field" id="shift2-task-code" name="task_code" placeholder="Ketik {{ $pendingTask->task_code }}" required>
                </div>
                <div class="table-wrap">
                    <table class="menu-table">
                        <thead><tr><th>Bahan</th><th>Satuan</th><th>Hitungan fisik Shift 2</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @foreach ($pendingTask->items as $index => $item)
                                <tr>
                                    <td><strong>{{ $item->material_name }}</strong><input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item->id }}"></td>
                                    <td>{{ $item->unit_name }}</td>
                                    <td><input class="field" name="items[{{ $index }}][counted_quantity]" type="number" min="0" max="999999999.999" step="0.001" required aria-label="Hitungan Shift 2 {{ $item->material_name }}"></td>
                                    <td><input class="field" name="items[{{ $index }}][note]" maxlength="500" aria-label="Catatan Shift 2 {{ $item->material_name }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="button" type="submit" style="margin-top:16px">Selesaikan Shift 2</button>
            </form>
        @endif
    </section>
@else
    <section class="panel">
        <div class="panel-head"><div><h2>Shift 1 · Mulai tugas audit</h2><p>Hitung semua bahan aktif. Setelah Shift 1 selesai, Shift 2 dengan akun berbeda akan mengulang hitungan.</p></div></div>
        @if ($materials->isEmpty())
            <div class="empty-state">Belum ada bahan untuk diaudit.</div>
        @else
            <form method="POST" action="{{ route('pos.stock-audits.shift1') }}" class="pos-order-form">
                @csrf
                <div class="field-group" style="margin-bottom:16px">
                    <label class="field-label" for="shift1-note">Catatan tugas (opsional)</label>
                    <input class="field" id="shift1-note" name="note" maxlength="1000">
                </div>
                <div class="table-wrap">
                    <table class="menu-table">
                        <thead><tr><th>Bahan</th><th>Satuan</th><th>Hitungan fisik Shift 1</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @foreach ($materials as $index => $material)
                                <tr>
                                    <td><strong>{{ $material->name }}</strong><input type="hidden" name="items[{{ $index }}][material_id]" value="{{ $material->id }}"></td>
                                    <td>{{ $material->unit->name }}</td>
                                    <td><input class="field" name="items[{{ $index }}][counted_quantity]" type="number" min="0" max="999999999.999" step="0.001" required aria-label="Hitungan Shift 1 {{ $material->name }}"></td>
                                    <td><input class="field" name="items[{{ $index }}][note]" maxlength="500" aria-label="Catatan Shift 1 {{ $material->name }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="button" type="submit" style="margin-top:16px">Selesaikan Shift 1 & buat kode TASK</button>
            </form>
        @endif
    </section>
@endif

<section class="panel">
    <div class="panel-head"><div><h2>Riwayat Audit Stock</h2><p>Selisih ditampilkan setelah kedua sesi selesai. Angka ini hanya laporan; penyesuaian resmi tetap melalui Stok Opname.</p></div></div>
    <div class="table-wrap">
        <table class="menu-table">
            <thead><tr><th>Kode TASK</th><th>Status</th><th>Shift 1</th><th>Shift 2</th><th>Hitungan bahan</th><th>Selisih antarshift</th><th>Detail</th></tr></thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td><strong>{{ $task->task_code }}</strong></td>
                        <td><span class="status-tag {{ $task->status === 'completed' ? 'paid' : 'pending' }}">{{ $task->status === 'completed' ? 'Selesai' : 'Menunggu Shift 2' }}</span></td>
                        <td>{{ $task->shift1User->name }}</td>
                        <td>{{ $task->shift2User?->name ?? '—' }}</td>
                        <td>{{ $task->items->count() }}</td>
                        <td>{{ $task->status === 'completed' ? $task->items->filter(fn ($item) => (float) $item->shift1_quantity !== (float) $item->shift2_quantity)->count().' bahan berbeda' : 'Menunggu hitungan kedua' }}</td>
                        <td>
                            <details>
                                <summary class="nav-link" style="cursor:pointer">Lihat</summary>
                                <ul class="order-lines">
                                    @foreach ($task->items as $item)
                                        <li>{{ $item->material_name }} ({{ $item->unit_name }}): stok sistem {{ number_format((float) $item->system_quantity, 3, ',', '.') }}, Shift 1 {{ number_format((float) $item->shift1_quantity, 3, ',', '.') }}@if ($item->shift2_quantity !== null), Shift 2 {{ number_format((float) $item->shift2_quantity, 3, ',', '.') }}, selisih S1–S2 {{ number_format((float) $item->shift1_quantity - (float) $item->shift2_quantity, 3, ',', '.') }}@endif</li>
                                    @endforeach
                                </ul>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada sesi audit stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $tasks->links() }}</div>
</section>
@endsection
