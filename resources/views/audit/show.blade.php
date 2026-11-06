@extends('layouts.app')

@section('title', 'Detail audit')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Rincian jejak aktivitas</p>
        <h1>Aktivitas #{{ $event->id }}</h1>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.audit.index') }}">Kembali ke audit</a>
    </div>
</div>
<section class="panel">
    <dl class="report-grid">
        <div><dt>Waktu</dt><dd>{{ $event->created_at->locale('id')->translatedFormat('d M Y, H:i:s') }}</dd></div>
        <div><dt>Pengguna</dt><dd>{{ $event->user?->name ?? 'Akun dihapus' }}</dd></div>
        <div><dt>Aksi</dt><dd>{{ $event->method }}</dd></div>
        <div><dt>Rute</dt><dd>{{ $event->route_name ?? '—' }}</dd></div>
        <div><dt>Objek</dt><dd>{{ $event->subject_type ? class_basename($event->subject_type).' #'.$event->subject_id : '—' }}</dd></div>
        <div><dt>Status HTTP</dt><dd>{{ $event->response_status }}</dd></div>
    </dl>
    <p class="cart-hint">Audit mencatat metadata aksi saja. Isi formulir, data pelanggan, dan kata sandi tidak disimpan.</p>
</section>
@endsection
