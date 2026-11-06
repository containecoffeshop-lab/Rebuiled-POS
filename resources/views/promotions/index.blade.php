@extends('layouts.app')

@section('title', 'Promo menu')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Promosi penjualan</p>
        <h1>Promo menu</h1>
        <p>Diskon otomatis untuk menu yang memenuhi jumlah minimum dan sasaran pelanggan.</p>
    </div>
    <div class="nav-links"><a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a></div>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2>Buat promo baru</h2><p>Promo terbaik per menu diterapkan. Kupon, jika ada, dihitung setelah diskon promo.</p></div>
    </div>
    <form method="POST" action="{{ route('pos.promotions.store') }}" class="pos-order-form">
        @csrf
        <div class="pos-layout" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));align-items:end">
            <div class="field-group">
                <label class="field-label" for="promotion-name">Nama promo</label>
                <input class="field" id="promotion-name" name="name" maxlength="120" minlength="3" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="promotion-type">Jenis diskon</label>
                <select class="field" id="promotion-type" name="discount_type" required>
                    <option value="percentage">Persentase</option>
                    <option value="fixed">Potongan Rupiah per item</option>
                </select>
            </div>
            <div class="field-group">
                <label class="field-label" for="promotion-value">Nilai diskon</label>
                <input class="field" id="promotion-value" name="discount_value" type="number" min="1" max="999999999999" step="1" required>
            </div>
            <div class="field-group">
                <label class="field-label" for="promotion-minimum">Jumlah minimum item eligible</label>
                <input class="field" id="promotion-minimum" name="minimum_quantity" type="number" min="1" max="600" value="1" required>
            </div>
        </div>

        <input type="hidden" name="all_menus" value="0">
        <label class="filter-chip" style="display:inline-flex;align-items:center;gap:8px;margin:12px 0">
            <input type="checkbox" name="all_menus" value="1" checked data-promotion-all="menus">
            Semua menu
        </label>
        <div class="menu-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));max-height:220px;overflow:auto" data-promotion-options="menus">
            @forelse ($menus as $menu)
                <label class="filter-chip" style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="menu_ids[]" value="{{ $menu->id }}">
                    {{ $menu->name }}
                </label>
            @empty
                <span class="order-meta">Belum ada menu aktif.</span>
            @endforelse
        </div>

        <input type="hidden" name="all_customers" value="0">
        <label class="filter-chip" style="display:inline-flex;align-items:center;gap:8px;margin:18px 0 12px">
            <input type="checkbox" name="all_customers" value="1" checked data-promotion-all="customers">
            Semua pelanggan
        </label>
        <div class="menu-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));max-height:220px;overflow:auto" data-promotion-options="customers">
            @forelse ($customers as $customer)
                <label class="filter-chip" style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}">
                    {{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}
                </label>
            @empty
                <span class="order-meta">Belum ada pelanggan terdaftar.</span>
            @endforelse
        </div>
        <button class="button" type="submit" style="margin-top:18px">Simpan promo</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head"><div><h2>Daftar promo</h2><p>Promo yang sudah digunakan tidak dihapus; nonaktifkan untuk menghentikan pemakaian berikutnya.</p></div></div>
    <div class="table-wrap">
        <table class="menu-table">
            <thead><tr><th>Nama</th><th>Diskon</th><th>Minimum</th><th>Sasaran menu</th><th>Pelanggan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($promotions as $promotion)
                    <tr>
                        <td><strong>{{ $promotion->name }}</strong></td>
                        <td>{{ $promotion->discount_type === 'percentage' ? $promotion->discount_value.'%' : 'Rp'.number_format($promotion->discount_value, 0, ',', '.') }} / item</td>
                        <td>{{ $promotion->minimum_quantity }}</td>
                        <td>{{ $promotion->all_menus ? 'Semua menu' : $promotion->menus->pluck('name')->join(', ') }}</td>
                        <td>{{ $promotion->all_customers ? 'Semua pelanggan' : $promotion->customers->pluck('name')->join(', ') }}</td>
                        <td><span class="status-tag {{ $promotion->is_active ? 'paid' : 'cancelled' }}">{{ $promotion->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('pos.promotions.toggle', $promotion) }}" onsubmit="return confirm('{{ $promotion->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' }} promo ini?')">
                                @csrf @method('PATCH')
                                <button class="button button-small {{ $promotion->is_active ? 'button-danger' : 'button-secondary' }}" type="submit">{{ $promotion->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada promo produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:18px">{{ $promotions->links() }}</div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-promotion-all]').forEach((toggle) => {
    const options = document.querySelector(`[data-promotion-options="${toggle.dataset.promotionAll}"]`);
    const update = () => {
        options.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.disabled = toggle.checked;
            if (toggle.checked) checkbox.checked = false;
        });
    };
    toggle.addEventListener('change', update);
    update();
});
</script>
@endpush
