<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($storeSettings['name'] ?? 'Rasa').' POS') · {{ $storeSettings['name'] ?? 'Rasa' }}</title>
    <meta name="description" content="Menu dan pemesanan {{ $storeSettings['name'] ?? 'Rasa' }}.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="--brand-primary: {{ $storeSettings['brand_primary'] ?? '#493123' }}; --brand-accent: {{ $storeSettings['brand_accent'] ?? '#947456' }}; --brand-background: {{ $storeSettings['brand_background'] ?? '#faf8f5' }}" @if (request()->routeIs('pos.index') && request('module', 'kasir') === 'kasir') class="pos-screen" @endif @if (request()->routeIs('pos.*') && ! request()->routeIs('pos.orders.receipt', 'pos.shift.report', 'pos.summary.pdf', 'pos.barcode-templates.print')) data-staff-workspace @endif>
    <header class="topbar">
        <div class="shell nav">
            <a class="brand" href="{{ route('home') }}">
                @if (filled($storeSettings['logo_path'] ?? null))
                    <img class="brand-logo" src="{{ asset($storeSettings['logo_path']) }}" alt="Logo {{ $storeSettings['name'] ?? 'toko' }}">
                @else
                    <span class="brand-mark"><x-icon name="cafe-outline" /></span>
                @endif
                <span>{{ $storeSettings['name'] ?? 'Rasa' }}<span style="color:#6e8d75">.</span></span>
            </a>
            <nav class="nav-links" aria-label="Navigasi utama">
                @auth
                    <div class="notification-center" data-notifications
                        data-user-id="{{ auth()->id() }}"
                        data-feed-url="{{ route('pos.notifications.index') }}"
                        data-stock-url="{{ route('pos.notifications.stock') }}"
                        data-read-all-url="{{ route('pos.notifications.read-all') }}">
                        <button class="button button-secondary notification-toggle" type="button" data-notification-toggle aria-expanded="false" aria-controls="notification-panel" aria-label="Notifikasi">
                            <x-icon name="notifications-outline" />
                            <span class="notification-badge" data-notification-badge hidden></span>
                        </button>
                        <section class="notification-panel" id="notification-panel" data-notification-panel hidden aria-label="Notifikasi terbaru">
                            <div class="notification-panel-head">
                                <strong>Notifikasi</strong>
                                <button class="button button-secondary button-small" type="button" data-notification-read-all>Tandai dibaca</button>
                            </div>
                            <div class="notification-panel-head notification-sound-controls">
                                <button class="button button-secondary button-small" type="button" data-notification-sound>Aktifkan suara</button>
                                <span class="cart-hint" data-notification-sound-status role="status">Suara belum aktif di perangkat ini.</span>
                            </div>
                            <ul class="notification-list" data-notification-list>
                                <li class="notification-empty">Memuat notifikasi…</li>
                            </ul>
                        </section>
                    </div>
                    <button class="button button-secondary printer-status-button" type="button" data-printer-dialog-open aria-haspopup="dialog" aria-controls="printer-dialog">
                        <x-icon name="bluetooth-outline" class="bluetooth-icon" />
                        <span class="printer-status-dot" data-printer-status-dot></span>
                        <span class="printer-status-label" data-printer-status-label>Bluetooth web</span>
                    </button>
                @endauth
                <a class="nav-link" href="{{ route('home') }}">Menu</a>
                @auth
                    <a class="button button-secondary" href="{{ route('pos.index', ['module' => 'kasir']) }}">Buka kasir</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="nav-link" type="submit">Keluar</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Masuk kasir</a>
                @endauth
            </nav>
        </div>
    </header>

    @auth
        <dialog class="printer-dialog" id="printer-dialog" data-printer-dialog aria-labelledby="printer-dialog-title">
            <div class="checkout-dialog-head">
                <div><p class="eyebrow" style="color:#65806c;margin:0 0 5px">Printer</p><h2 id="printer-dialog-title">Metode cetak</h2></div>
                <button class="button button-secondary button-small" type="button" data-printer-dialog-close>Tutup</button>
            </div>
            <section class="printer-method-card">
                <h3>Printer yang dipasangkan ke perangkat</h3>
                <p class="cart-hint">Pada halaman struk, pilih cetak browser untuk membuka dialog printer dari perangkat. Ketersediaan dan status printer ditentukan Android, iPhone, Windows, atau browser dan tidak dapat dibaca langsung oleh situs.</p>
            </section>
            <section class="printer-method-card">
                <h3>Koneksi Web Bluetooth (percobaan)</h3>
                <p class="cart-hint" data-printer-support role="status">Memeriksa dukungan browser…</p>
                <p class="printer-connection-state" data-printer-connection-state role="status">Belum tersambung</p>
                <div class="printer-dialog-actions">
                    <button class="button" type="button" data-printer-connect>Hubungkan printer</button>
                    <button class="button button-secondary" type="button" data-printer-disconnect hidden>Putuskan</button>
                    <button class="button button-secondary" type="button" data-printer-web-print hidden>Cetak struk via Bluetooth web</button>
                </div>
                <p class="cart-hint">Web Bluetooth memerlukan browser dan printer BLE/GATT yang kompatibel. Printer Bluetooth Classic/SPP dan ESC/POS VSC belum terverifikasi; bila koneksi atau karakteristik cetak tidak tersedia, gunakan dialog cetak perangkat.</p>
            </section>
        </dialog>
    @endauth

    <main class="shell">
        @if (session('success'))
            <div class="alert" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Periksa kembali:</strong>
                <ul style="margin:7px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="shell page-footer">
        <span>{{ $storeSettings['name'] ?? 'Rasa' }}{{ filled($storeSettings['receipt_footer'] ?? null) ? ' · '.$storeSettings['receipt_footer'] : '' }}</span>
        <span style="float:right">{{ collect([$storeSettings['address'] ?? null, $storeSettings['phone'] ?? null])->filter()->join(' · ') ?: 'Semua harga dalam Rupiah.' }}</span>
    </footer>
    @stack('scripts')
</body>
</html>
