@extends('layouts.app')

@section('title', 'Pengguna')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pengaturan akses</p>
        <h1>Pengguna</h1>
        <p>Kelola akses pengelola dan kasir. Akun nonaktif tidak dapat masuk atau menggunakan aplikasi.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Tambah pengguna</h2><p>Gunakan kata sandi minimal 10 karakter dan bagikan secara aman.</p></div>
    </div>
    <form class="pos-layout" method="POST" action="{{ route('pos.users.store') }}" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));align-items:end">
        @csrf
        <div class="field-group">
            <label class="field-label" for="new-user-name">Nama</label>
            <input class="field" id="new-user-name" name="name" maxlength="120" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-user-email">Email</label>
            <input class="field" id="new-user-email" name="email" type="email" maxlength="255" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-user-role">Peran</label>
            <select class="field" id="new-user-role" name="role" required>
                <option value="cashier">Kasir</option>
                <option value="manager">Pengelola</option>
            </select>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-user-password">Kata sandi</label>
            <input class="field" id="new-user-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="new-user-password-confirmation">Ulangi kata sandi</label>
            <input class="field" id="new-user-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
        </div>
        <button class="button" type="submit">Buat akun</button>
    </form>
</section>

<section class="panel">
    <form method="GET" action="{{ route('pos.users.index') }}" class="filter-row">
        <div class="field-group" style="flex:1;min-width:220px">
            <label class="field-label" for="user-search">Cari nama atau email</label>
            <input class="field" id="user-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120">
        </div>
        <button class="button button-secondary" type="submit">Cari</button>
        <a class="button button-secondary" href="{{ route('pos.users.index') }}">Reset</a>
    </form>
    <div class="table-wrap" style="margin-top:20px">
        <table class="menu-table">
            <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Ubah data</th><th>Akses</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong>@if ($user->is(auth()->user())) <span class="order-meta">(akun Anda)</span>@endif</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role === 'manager' ? 'Pengelola' : 'Kasir' }}</td>
                        <td><span class="status-tag {{ $user->is_active ? 'paid' : 'cancelled' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <details>
                                <summary class="nav-link" style="padding:5px 0;cursor:pointer">Edit</summary>
                                <form class="pos-order-form" method="POST" action="{{ route('pos.users.update', $user) }}" style="min-width:230px;margin-top:8px">
                                    @csrf @method('PATCH')
                                    <div class="field-group">
                                        <label class="field-label" for="user-name-{{ $user->id }}">Nama</label>
                                        <input class="field" id="user-name-{{ $user->id }}" name="name" value="{{ $user->name }}" maxlength="120" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="user-email-{{ $user->id }}">Email</label>
                                        <input class="field" id="user-email-{{ $user->id }}" name="email" type="email" value="{{ $user->email }}" maxlength="255" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="user-role-{{ $user->id }}">Peran</label>
                                        <select class="field" id="user-role-{{ $user->id }}" name="role" required>
                                            <option value="cashier" @selected($user->role === 'cashier')>Kasir</option>
                                            <option value="manager" @selected($user->role === 'manager')>Pengelola</option>
                                        </select>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="user-password-{{ $user->id }}">Kata sandi baru (opsional)</label>
                                        <input class="field" id="user-password-{{ $user->id }}" name="password" type="password" minlength="10" autocomplete="new-password">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label" for="user-password-confirmation-{{ $user->id }}">Ulangi kata sandi baru</label>
                                        <input class="field" id="user-password-confirmation-{{ $user->id }}" name="password_confirmation" type="password" minlength="10" autocomplete="new-password">
                                    </div>
                                    <button class="button button-small" type="submit">Simpan</button>
                                </form>
                            </details>
                        </td>
                        <td>
                            @if ($user->role === 'cashier')
                                <details>
                                    <summary class="nav-link" style="padding:5px 0;cursor:pointer">Akses modul</summary>
                                    <form class="pos-order-form" method="POST" action="{{ route('pos.users.permissions', $user) }}">
                                        @csrf @method('PUT')
                                        <p class="cart-hint">Akses berlaku untuk seluruh tindakan modul. Pengguna dan pengaturan toko selalu khusus pengelola.</p>
                                        @foreach (\App\Services\ModuleAccess::MODULES as $key => $label)
                                            <label class="field-label"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $user->module_permissions ?? \App\Services\ModuleAccess::DEFAULTS, true))> {{ $label }}</label>
                                        @endforeach
                                        <button class="button button-small" type="submit">Simpan akses</button>
                                    </form>
                                </details>
                            @endif
                            @if (! $user->is(auth()->user()))
                                <form method="POST" action="{{ route('pos.users.toggle', $user) }}" onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} akun {{ $user->name }}?')">
                                    @csrf @method('PATCH')
                                    <button class="button button-small {{ $user->is_active ? 'button-danger' : 'button-secondary' }}" type="submit">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @else
                                <span class="order-meta">Tidak dapat menonaktifkan diri sendiri</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Tidak ada pengguna yang cocok dengan pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px">{{ $users->links() }}</div>
</section>
@endsection
