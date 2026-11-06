@extends('layouts.app')

@section('title', 'Pengaturan awal')

@section('content')
<div class="auth-wrap">
    <section class="auth-card">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">R</span><span>Pengaturan awal</span></a>
        <h1>Buat akun pengelola.</h1>
        <p>Akun ini hanya dapat dibuat sekali dan diperlukan untuk membuka shift kasir serta mengatur menu. Kata sandi minimal 10 karakter.</p>
        <form method="POST" action="{{ route('setup.store') }}">
            @csrf
            @if (config('app.initial_setup_token'))
                <div class="field-group">
                    <label class="field-label" for="setup_token">Kode setup sekali pakai</label>
                    <input class="field" id="setup_token" name="setup_token" type="password" autocomplete="one-time-code" required>
                </div>
            @endif
            <div class="field-group">
                <label class="field-label" for="name">Nama</label>
                <input class="field" id="name" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required autofocus>
            </div>
            <div class="field-group">
                <label class="field-label" for="email">Email</label>
                <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="password">Kata sandi</label>
                <input class="field" id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="password_confirmation">Ulangi kata sandi</label>
                <input class="field" id="password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
            </div>
            <button class="button full-width" type="submit">Buat akun & lanjut</button>
        </form>
    </section>
</div>
@endsection
