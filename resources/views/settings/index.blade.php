@extends('layouts.app')

@section('title', 'Pengaturan toko')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pengelola</p><h1>Pengaturan toko</h1><p>Identitas toko ditampilkan pada katalog dan halaman aplikasi.</p></div>
    <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
</div>

<section class="panel">
    <h2>Jadwal shift</h2>
    <p class="cart-hint">Jam hanya informasi; pembukaan shift tidak dibatasi waktu. Shift dapat melewati tengah malam.</p>
    <form method="POST" action="{{ route('pos.settings.shifts.update') }}" class="pos-order-form">
        @csrf @method('PUT')
        @foreach ([1, 2] as $number)
            <div class="inline-form">
                <strong>Shift {{ $number }}</strong>
                <label>Mulai <input class="field" type="time" name="shift_{{ $number }}_start" value="{{ old('shift_'.$number.'_start', $settings['shift_'.$number.'_start'] ?? '') }}"></label>
                <label>Selesai <input class="field" type="time" name="shift_{{ $number }}_end" value="{{ old('shift_'.$number.'_end', $settings['shift_'.$number.'_end'] ?? '') }}"></label>
            </div>
        @endforeach
        <button class="button" type="submit">Simpan jadwal shift</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Notifikasi per peran</h2><p>Jenis yang dipilih dikirim untuk peristiwa baru. Notifikasi lama tetap tersedia; suara diaktifkan masing-masing perangkat pada lonceng.</p></div></div>
    <form method="POST" action="{{ route('pos.settings.notifications.update') }}">
        @csrf @method('PUT')
        @foreach (\App\Services\PosNotificationService::ROLES as $role => $label)
            @php
                $saved = isset($settings['notification_categories_'.$role]) ? json_decode($settings['notification_categories_'.$role], true, flags: JSON_THROW_ON_ERROR) : array_values(array_filter(array_keys(\App\Services\PosNotificationService::CATEGORIES), fn ($category) => $role === 'manager' || $category !== 'stock'));
                $selected = old('categories.'.$role, $saved);
            @endphp
            <fieldset>
                <legend>{{ $label }}</legend>
                @foreach (\App\Services\PosNotificationService::CATEGORIES as $category => $description)
                    <label class="field-label"><input type="checkbox" name="categories[{{ $role }}][]" value="{{ $category }}" @checked(in_array($category, $selected, true))> {{ $description }}</label>
                @endforeach
            </fieldset>
        @endforeach
        <button class="button" type="submit">Simpan notifikasi</button>
    </form>
</section>

<section class="panel">
    <form class="pos-order-form" method="POST" action="{{ route('pos.settings.update') }}" enctype="multipart/form-data" style="max-width:680px" data-feature-label="identitas dan tampilan toko">
        @csrf @method('PUT')
        <div class="field-group">
            <label class="field-label" for="store-name">Nama toko</label>
            <input class="field" id="store-name" name="name" value="{{ old('name', $settings['name'] ?? 'Rasa') }}" maxlength="120" required>
        </div>
        <div class="field-group">
            <label class="field-label" for="store-address">Alamat (opsional)</label>
            <textarea class="field" id="store-address" name="address" rows="3" maxlength="500">{{ old('address', $settings['address'] ?? '') }}</textarea>
        </div>
        <div class="field-group">
            <label class="field-label" for="store-phone">Nomor telepon (opsional)</label>
            <input class="field" id="store-phone" name="phone" value="{{ old('phone', $settings['phone'] ?? '') }}" maxlength="40">
        </div>
        <div class="field-group">
            <label class="field-label" for="store-footer">Pesan footer (opsional)</label>
            <input class="field" id="store-footer" name="receipt_footer" value="{{ old('receipt_footer', $settings['receipt_footer'] ?? '') }}" maxlength="240" placeholder="Terima kasih telah berkunjung">
        </div>
        <h2 class="settings-subheading">Logo dan tampilan katalog</h2>
        <div class="field-group">
            <label class="field-label" for="store-logo">Logo toko (opsional)</label>
            @if (filled($settings['logo_path'] ?? null))
                <img class="upload-preview logo-preview" src="{{ asset($settings['logo_path']) }}" alt="Logo toko saat ini">
                <label class="field-label"><input type="checkbox" name="remove_logo" value="1"> Hapus logo saat disimpan</label>
            @endif
            <input class="field" id="store-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" data-image-preview-input data-image-preview-target="store-logo-preview">
            <small class="cart-hint">JPG, PNG, atau WebP, maksimal 5 MB. Logo diperkecil maksimal 512 px dan dikompresi; PNG transparan dipertahankan.</small>
            <img class="upload-preview logo-preview" id="store-logo-preview" alt="Pratinjau logo toko" hidden>
        </div>
        <div class="settings-color-grid">
            <div class="field-group">
                <label class="field-label" for="brand-primary">Warna utama</label>
                <input class="field color-field" id="brand-primary" name="brand_primary" type="color" value="{{ old('brand_primary', $settings['brand_primary'] ?? '#493123') }}" data-theme-color="primary">
            </div>
            <div class="field-group">
                <label class="field-label" for="brand-accent">Warna aksen</label>
                <input class="field color-field" id="brand-accent" name="brand_accent" type="color" value="{{ old('brand_accent', $settings['brand_accent'] ?? '#947456') }}" data-theme-color="accent">
            </div>
            <div class="field-group">
                <label class="field-label" for="brand-background">Warna latar</label>
                <input class="field color-field" id="brand-background" name="brand_background" type="color" value="{{ old('brand_background', $settings['brand_background'] ?? '#faf8f5') }}" data-theme-color="background">
            </div>
        </div>
        <p class="cart-hint">Warna disesuaikan untuk menjaga keterbacaan; mode gelap mengikuti perangkat.</p>
        <div class="theme-preview" data-theme-preview>
            <span class="theme-preview-label">Pratinjau warna</span>
            <strong>{{ old('name', $settings['name'] ?? 'Nama toko') }}</strong>
            <span>Contoh tampilan katalog dengan warna pilihan.</span>
            <button class="button button-small" type="button">Tombol utama</button>
        </div>
        <div class="field-group">
            <label class="field-label" for="store-hero-title">Judul halaman menu pelanggan</label>
            <input class="field" id="store-hero-title" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}" maxlength="120" placeholder="Temukan rasa yang pas untuk hari ini.">
        </div>
        <div class="field-group">
            <label class="field-label" for="store-hero-description">Deskripsi halaman menu pelanggan</label>
            <textarea class="field" id="store-hero-description" name="hero_description" rows="3" maxlength="300" placeholder="Sampaikan cerita singkat tentang toko dan menu Anda.">{{ old('hero_description', $settings['hero_description'] ?? '') }}</textarea>
        </div>
        <button class="button" type="submit">Simpan pengaturan</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Rekening transfer</h2>
            <p>Rekening aktif tersedia untuk dipilih kasir saat mencatat pembayaran transfer. Rekening yang dipakai transaksi lama tidak dapat dihapus; nonaktifkan jika sudah tidak digunakan.</p>
        </div>
    </div>
    <form class="pos-order-form" method="POST" action="{{ route('pos.settings.accounts.update') }}">
        @csrf @method('PUT')
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Nama bank</th><th>Nomor rekening</th><th>Nama pemilik</th><th>Status</th><th></th></tr></thead>
                <tbody data-account-rows data-next-index="{{ $bankAccounts->count() }}">
                    @foreach ($bankAccounts as $index => $account)
                        <tr data-account-row data-existing-account>
                            <td>
                                <input type="hidden" name="accounts[{{ $index }}][id]" value="{{ $account->id }}">
                                <input class="field" name="accounts[{{ $index }}][bank_name]" value="{{ $account->bank_name }}" maxlength="80" aria-label="Nama bank" required>
                            </td>
                            <td><input class="field" name="accounts[{{ $index }}][account_number]" value="{{ $account->account_number }}" maxlength="80" aria-label="Nomor rekening" required></td>
                            <td><input class="field" name="accounts[{{ $index }}][account_name]" value="{{ $account->account_name }}" maxlength="120" aria-label="Nama pemilik rekening" required></td>
                            <td>
                                <input type="hidden" name="accounts[{{ $index }}][is_active]" value="0">
                                <label><input type="checkbox" name="accounts[{{ $index }}][is_active]" value="1" @checked($account->is_active)> Aktif</label>
                            </td>
                            <td><span class="order-meta">Riwayat transaksi dipertahankan</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($bankAccounts->isEmpty())
            <p class="empty-state">Belum ada rekening transfer yang dikonfigurasi.</p>
        @endif
        <div class="filter-row">
            <button class="button button-secondary" type="button" data-add-account>Tambah rekening</button>
            <button class="button" type="submit">Simpan rekening</button>
        </div>
    </form>
    <template data-account-template>
        <tr data-account-row>
            <td><input type="hidden" name="accounts[__INDEX__][id]" value=""><input class="field" name="accounts[__INDEX__][bank_name]" maxlength="80" aria-label="Nama bank" required></td>
            <td><input class="field" name="accounts[__INDEX__][account_number]" maxlength="80" aria-label="Nomor rekening" required></td>
            <td><input class="field" name="accounts[__INDEX__][account_name]" maxlength="120" aria-label="Nama pemilik rekening" required></td>
            <td><input type="hidden" name="accounts[__INDEX__][is_active]" value="0"><label><input type="checkbox" name="accounts[__INDEX__][is_active]" value="1" checked> Aktif</label></td>
            <td><button class="button button-danger button-small" type="button" data-remove-account>Hapus</button></td>
        </tr>
    </template>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Pajak dan biaya layanan</h2>
            <p>Pengaturan ini berlaku pada pesanan kasir dan pelanggan. Belum ada tarif bawaan; aktifkan hanya nilai yang telah ditetapkan toko.</p>
        </div>
    </div>
    <div class="alert" role="note">PJK dan OPR dihitung dari subtotal setelah diskon promo/kupon. Masukkan angka persen seperti <strong>10%</strong> atau nominal rupiah seperti <strong>2000</strong>. Nilai tiap transaksi disimpan sebagai snapshot dan tidak berubah saat pengaturan diperbarui.</div>
    <form class="pos-order-form" method="POST" action="{{ route('pos.settings.charges.update') }}">
        @csrf @method('PUT')
        <div class="table-wrap">
            <table class="menu-table">
                <thead><tr><th>Jenis</th><th>Nama pada nota</th><th>Persentase / nominal</th><th>Aktif</th><th></th></tr></thead>
                <tbody data-charge-rows data-next-index="{{ $charges->count() }}">
                    @foreach ($charges as $index => $charge)
                        <tr data-charge-row>
                            <td>
                                <select class="field" name="charges[{{ $index }}][code]" aria-label="Jenis biaya" required>
                                    <option value="PJK" @selected($charge->code === 'PJK')>PJK · Pajak</option>
                                    <option value="OPR" @selected($charge->code === 'OPR')>OPR · Biaya layanan</option>
                                </select>
                            </td>
                            <td><input class="field" name="charges[{{ $index }}][name]" value="{{ $charge->name }}" maxlength="120" aria-label="Nama biaya" required></td>
                            <td><input class="field" name="charges[{{ $index }}][value]" value="{{ $charge->value }}" maxlength="24" placeholder="10% atau 2000" aria-label="Persentase atau nominal" required></td>
                            <td>
                                <input type="hidden" name="charges[{{ $index }}][is_active]" value="0">
                                <label><input type="checkbox" name="charges[{{ $index }}][is_active]" value="1" @checked($charge->is_active)> Aktif</label>
                            </td>
                            <td><button class="button button-danger button-small" type="button" data-remove-charge>Hapus</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($charges->isEmpty())
            <p class="empty-state">Belum ada pajak atau biaya layanan yang dikonfigurasi.</p>
        @endif
        <div class="filter-row">
            <button class="button button-secondary" type="button" data-add-charge>Tambah PJK/OPR</button>
            <button class="button" type="submit">Simpan pajak dan biaya</button>
        </div>
    </form>
    <template data-charge-template>
        <tr data-charge-row>
            <td>
                <select class="field" name="charges[__INDEX__][code]" aria-label="Jenis biaya" required>
                    <option value="PJK">PJK · Pajak</option>
                    <option value="OPR">OPR · Biaya layanan</option>
                </select>
            </td>
            <td><input class="field" name="charges[__INDEX__][name]" maxlength="120" aria-label="Nama biaya" required></td>
            <td><input class="field" name="charges[__INDEX__][value]" maxlength="24" placeholder="10% atau 2000" aria-label="Persentase atau nominal" required></td>
            <td>
                <input type="hidden" name="charges[__INDEX__][is_active]" value="0">
                <label><input type="checkbox" name="charges[__INDEX__][is_active]" value="1"> Aktif</label>
            </td>
            <td><button class="button button-danger button-small" type="button" data-remove-charge>Hapus</button></td>
        </tr>
    </template>
</section>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Poin loyalitas</h2>
            <p>Atur perolehan dan nilai tukar poin. Tidak ada tarif bawaan; tiap aturan dapat dibiarkan kosong untuk tetap nonaktif.</p>
        </div>
    </div>
    <div class="alert" role="note">Poin diperoleh dari subtotal setelah promo, kupon, dan penukaran poin, tanpa menghitung PJK/OPR. Poin hanya masuk setelah transaksi lunas. Penukaran saat ini hanya tersedia di kasir; penukaran pesanan online menunggu verifikasi OTP.</div>
    <form class="pos-order-form" method="POST" action="{{ route('pos.settings.loyalty.update') }}" style="max-width:680px">
        @csrf @method('PUT')
        <div class="field-group">
            <label class="field-label" for="loyalty-earning-threshold">Belanja bersih untuk memperoleh 1 poin (Rp)</label>
            <input class="field" id="loyalty-earning-threshold" name="earning_threshold" type="number" min="1" max="999999999999" step="1" value="{{ old('earning_threshold', $settings['loyalty_earning_threshold'] ?? '') }}" placeholder="Kosong = perolehan nonaktif">
        </div>
        <div class="field-group">
            <label class="field-label" for="loyalty-point-value">Nilai diskon per poin (Rp)</label>
            <input class="field" id="loyalty-point-value" name="point_value" type="number" min="1" max="999999999999" step="1" value="{{ old('point_value', $settings['loyalty_point_value'] ?? '') }}" placeholder="Kosong = penukaran nonaktif">
        </div>
        <button class="button" type="submit">Simpan pengaturan poin</button>
    </form>
</section>
@endsection
