@extends('layouts.app')
@section('title', 'Cycle count')
@section('content')
<div class="dashboard-head">
    <div><h1>Cycle count</h1><p>Hitung bahan yang dipilih dan kirim hasil untuk persetujuan pengelola.</p></div>
    <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
</div>
@if (! $shift)
<section class="panel">
    <h2>Buka shift untuk menghitung</h2>
    <form method="POST" action="{{ route('pos.shift.open') }}" class="inline-form">
        @csrf
        <label>Shift <select class="field" name="shift_number" required><option value="">Pilih shift</option><option value="1">Shift 1</option><option value="2">Shift 2</option></select></label>
        <label>Uang kas awal <input class="field" type="number" min="0" max="999999999999" name="starting_cash" value="0" required></label>
        <button class="button" type="submit">Buka shift</button>
    </form>
</section>
@elseif (! \App\Services\ModuleAccess::allows(auth()->user(), 'kasir'))
<section class="panel">
    <h2>Shift {{ $shift->shift_number ?? 'lama tanpa nomor' }} sedang berjalan</h2>
    <p>Kas diharapkan Rp{{ number_format($expectedCash, 0, ',', '.') }}; retur dan pengeluaran di luar shift tidak dihitung.</p>
    <form method="POST" action="{{ route('pos.shift.close') }}" class="inline-form">
        @csrf
        <label>Uang kas akhir <input class="field" type="number" min="0" max="999999999999" name="ending_cash" required></label>
        <label>Alasan selisih jika ada <input class="field" name="closing_note" maxlength="1000"></label>
        <button class="button button-danger" type="submit">Tutup shift</button>
    </form>
</section>
@endif
@if (auth()->user()->role === 'manager')
<section class="panel">
    <h2>Buat tugas</h2>
    <form method="POST" action="{{ route('pos.cycle-counts.store') }}" class="pos-order-form">
        @csrf
        <div class="inline-form">
            <label>Tanggal tugas <input class="field" type="date" name="task_date" value="{{ old('task_date', now()->toDateString()) }}" required></label>
            <label>Shift <select class="field" name="shift_number" required><option value="1">Shift 1</option><option value="2">Shift 2</option></select></label>
            <label>Catatan <input class="field" name="note" maxlength="1000"></label>
        </div>
        <fieldset><legend>Pilih bahan</legend>
            @forelse ($materials as $material)
                <label class="field-label"><input type="checkbox" name="materials[]" value="{{ $material->id }}"> {{ $material->name }} ({{ $material->unit->name }})</label>
            @empty
                <p>Belum ada bahan untuk dihitung.</p>
            @endforelse
        </fieldset>
        <button class="button" type="submit" @disabled($materials->isEmpty())>Buat tugas</button>
    </form>
</section>
@endif
<section class="panel">
    <h2>Daftar tugas</h2>
    <p class="cart-hint">Stok sistem dicatat saat hitungan dikirim. Persetujuan menerapkan selisih terhadap stok terkini, bukan mengganti stok dengan angka hitungan.</p>
    @forelse ($tasks as $task)
        <article class="order-card">
            <h3>Tugas #{{ $task->id }} · {{ $task->task_date->format('d/m/Y') }} · Shift {{ $task->shift_number }}</h3>
            <p>{{ ['pending' => 'Menunggu hitungan', 'review' => 'Menunggu persetujuan', 'approved' => 'Disetujui'][$task->status] ?? $task->status }} · Dibuat {{ $task->creator->name }} @if($task->counter) · Dihitung {{ $task->counter->name }} pada {{ $task->counted_at->format('d/m/Y H:i') }} @endif @if($task->approver) · Disetujui {{ $task->approver->name }} @endif</p>
            <details class="order-card-details" @if ($errors->any()) open @endif>
            <summary class="button button-secondary button-small">Detail dan tindakan tugas</summary>
            @if ($task->note)<p>{{ $task->note }}</p>@endif
            @if ($task->status === 'pending' && $shift && $shift->shift_number === $task->shift_number)
                <form method="POST" action="{{ route('pos.cycle-counts.count', $task) }}" class="pos-order-form">
                    @csrf
                    @foreach ($task->items as $item)
                        <div class="inline-form">
                            <strong>{{ $item->material_name }} ({{ $item->unit_name }})</strong>
                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                            <label>Jumlah fisik <input class="field" type="number" min="0" max="999999999.999" step="0.001" name="items[{{ $loop->index }}][quantity]" required></label>
                            <label>Catatan <input class="field" name="items[{{ $loop->index }}][note]" maxlength="500"></label>
                        </div>
                    @endforeach
                    <button class="button" type="submit">Kirim hasil hitung</button>
                </form>
            @elseif ($task->status === 'pending')
                <p>Buka shift {{ $task->shift_number }} untuk mengisi tugas ini.</p>
            @else
                <div class="table-wrap"><table class="menu-table">
                    <thead><tr><th>Bahan</th><th>Sistem saat hitung</th><th>Fisik</th><th>Selisih</th><th>Sebelum → sesudah approval</th><th>Catatan</th></tr></thead>
                    <tbody>@foreach ($task->items as $item)
                        <tr><td>{{ $item->material_name }} ({{ $item->unit_name }})</td><td>{{ $item->system_quantity }}</td><td>{{ $item->counted_quantity }}</td><td>{{ $item->difference }}</td><td>{{ $item->stock_before_approval ?? '–' }} → {{ $item->stock_after_approval ?? '–' }}</td><td>{{ $item->note }}</td></tr>
                    @endforeach</tbody>
                </table></div>
                @if (auth()->user()->role === 'manager' && $task->status === 'review')
                    <form method="POST" action="{{ route('pos.cycle-counts.approve', $task) }}">@csrf <button class="button" type="submit">Setujui dan terapkan selisih</button></form>
                    <form method="POST" action="{{ route('pos.cycle-counts.recount', $task) }}">@csrf <button class="button button-secondary" type="submit">Kembalikan untuk hitung ulang</button></form>
                @endif
            @endif
            </details>
        </article>
    @empty
        <p>Tidak ada tugas yang tersedia.</p>
    @endforelse
    {{ $tasks->links() }}
</section>
@endsection
