@extends('layouts.app')

@section('title', 'Masuk kasir')

@section('content')
<div class="auth-wrap">
    <section class="auth-card">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">R</span><span>Area kasir</span></a>
        <h1>Selamat datang kembali.</h1>
        <p>Masuk untuk mengelola transaksi, menu, dan pesanan dapur.</p>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field-group">
                <label class="field-label" for="email">Email</label>
                <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
            <div class="field-group">
                <label class="field-label" for="password">Kata sandi</label>
                <input class="field" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <p class="cart-hint">Login aktif sampai pukul 00.00 mengikuti waktu toko.</p>
            <button class="button full-width" type="submit">Masuk ke kasir</button>
        </form>
        @if (! \Illuminate\Support\Facades\DB::table('setup_state')->where('id', 1)->value('is_complete'))
            <p style="margin:19px 0 0;text-align:center"><a class="nav-link" href="{{ route('setup') }}">Buat akun pengelola pertama</a></p>
        @endif
    </section>
</div>
@endsection
