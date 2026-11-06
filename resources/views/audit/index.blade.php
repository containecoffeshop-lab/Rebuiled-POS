@extends('layouts.app')

@section('title', 'Audit aktivitas')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Keamanan dan jejak perubahan</p>
        <h1>Audit aktivitas</h1>
        <p>Riwayat pengiriman aksi perubahan melalui POS. Isi formulir dan kata sandi tidak disimpan dalam log.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
        <a class="button" href="{{ route('pos.stock-audits.index') }}">Audit Stock</a>
        <a class="button button-secondary" href="{{ route('pos.audit.index', array_merge(request()->query(), ['export' => 'csv'])) }}">Unduh CSV</a>
    </div>
</div>

<section class="panel">
    <form method="GET" action="{{ route('pos.audit.index') }}" class="filter-row" style="align-items:end">
        <div class="field-group" style="flex:1;min-width:210px">
            <label class="field-label" for="audit-search">Cari rute, objek, atau pengguna</label>
            <input class="field" id="audit-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120">
        </div>
        <div class="field-group">
            <label class="field-label" for="audit-event">Aksi</label>
            <select class="field" id="audit-event" name="event">
                <option value="">Semua aksi</option>
                @foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                    <option value="{{ $method }}" @selected(($filters['event'] ?? '') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <div class="field-group">
            <label class="field-label" for="audit-date-from">Dari tanggal</label>
            <input class="field" id="audit-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="field-group">
            <label class="field-label" for="audit-date-to">Sampai tanggal</label>
            <input class="field" id="audit-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <button class="button button-secondary" type="submit">Terapkan</button>
        <a class="button button-secondary" href="{{ route('pos.audit.index') }}">Reset</a>
    </form>

    <div class="table-wrap" style="margin-top:20px">
        <table class="menu-table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Rute</th><th>Objek</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td>{{ $event->created_at->locale('id')->translatedFormat('d M Y, H:i:s') }}</td>
                        <td>{{ $event->user?->name ?? 'Akun dihapus' }}</td>
                        <td>{{ $event->method }}</td>
                        <td>{{ $event->route_name ?? $event->properties['path'] ?? '—' }}</td>
                        <td>{{ $event->subject_type ? class_basename($event->subject_type).' #'.$event->subject_id : '—' }}</td>
                        <td><span class="status-tag {{ $event->response_status < 400 ? 'paid' : 'cancelled' }}">{{ $event->response_status }}</span></td>
                        <td><a class="button button-secondary button-small" href="{{ route('pos.audit.show', $event) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada aktivitas pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px">{{ $events->links() }}</div>
</section>
@endsection
