@extends('layouts.app')

@section('title', 'Pengeluaran')

@section('content')
<div class="dashboard-head">
    <div>
        <p class="eyebrow" style="color:#65806c;margin-bottom:5px">Operasional</p>
        <h1>Pengeluaran.</h1>
        <p>Catat biaya operasional, kelompokkan, dan pantau pembayaran yang masih tersisa.</p>
    </div>
    <div class="nav-links">
        <a class="button button-secondary" href="{{ route('pos.index') }}">Kembali ke kasir</a>
        <a class="button button-secondary" href="#buat-pengeluaran">Catat pengeluaran</a>
    </div>
</div>

<section class="panel" id="kategori-pengeluaran">
    <div class="panel-head">
        <div><h2>Kategori & subkategori</h2><p>Penghapusan kategori tidak disediakan agar riwayat pengeluaran tetap utuh.</p></div>
    </div>
    <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div class="pos-order-form">
            <h3 style="margin:0 0 15px;font-size:15px">Tambah kategori</h3>
            <form method="POST" action="{{ route('pos.expenses.categories.store') }}">
                @csrf
                <div class="field-group">
                    <label class="field-label" for="expense-category-name">Nama kategori</label>
                    <input class="field" id="expense-category-name" name="name" value="{{ old('name') }}" minlength="3" maxlength="120" required>
                </div>
                <button class="button" type="submit">Simpan kategori</button>
            </form>

            @if ($categories->isNotEmpty())
                <div class="table-wrap" style="margin-top:20px">
                    <table class="menu-table">
                        <thead><tr><th>Kategori</th><th>Catatan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <strong>{{ $category->name }}</strong>
                                        @if ($category->subcategories->isNotEmpty())
                                            <ul class="order-lines">
                                                @foreach ($category->subcategories as $subcategory)
                                                    <li>{{ $subcategory->name }}
                                                        <details style="display:inline-block">
                                                            <summary class="nav-link" style="display:inline;padding:2px 6px;cursor:pointer">Edit</summary>
                                                            <form method="POST" action="{{ route('pos.expenses.subcategories.update', $subcategory) }}" class="inline-form">
                                                                @csrf @method('PATCH')
                                                                <label class="visually-hidden" for="edit-subcategory-{{ $subcategory->id }}">Nama subkategori</label>
                                                                <input class="field" id="edit-subcategory-{{ $subcategory->id }}" name="name" value="{{ $subcategory->name }}" minlength="3" maxlength="120" required>
                                                                <button class="button button-small" type="submit">Simpan</button>
                                                            </form>
                                                        </details>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td>{{ $category->expenses_count }} pengeluaran</td>
                                    <td>
                                        <details>
                                            <summary class="nav-link" style="padding:5px 0;cursor:pointer">Edit nama</summary>
                                            <form method="POST" action="{{ route('pos.expenses.categories.update', $category) }}" class="inline-form">
                                                @csrf @method('PATCH')
                                                <label class="visually-hidden" for="edit-expense-category-{{ $category->id }}">Nama kategori</label>
                                                <input class="field" id="edit-expense-category-{{ $category->id }}" name="name" value="{{ $category->name }}" minlength="3" maxlength="120" required>
                                                <button class="button button-small" type="submit">Simpan</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="pos-order-form">
            <h3 style="margin:0 0 15px;font-size:15px">Tambah subkategori</h3>
            @if ($categories->isEmpty())
                <p class="cart-hint">Buat kategori terlebih dahulu.</p>
            @else
                <form method="POST" action="{{ route('pos.expenses.subcategories.store') }}">
                    @csrf
                    <div class="field-group">
                        <label class="field-label" for="subcategory-category">Kategori</label>
                        <select class="field" id="subcategory-category" name="expense_category_id" required>
                            <option value="">Pilih kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="subcategory-name">Nama subkategori</label>
                        <input class="field" id="subcategory-name" name="name" value="{{ old('name') }}" minlength="3" maxlength="120" required>
                    </div>
                    <button class="button" type="submit">Simpan subkategori</button>
                </form>
            @endif
        </div>
    </div>
</section>

<section class="panel" id="buat-pengeluaran">
    <div class="panel-head">
        <div><h2>Catat pengeluaran</h2><p>Nilai dicatat dalam Rupiah bulat. Pembayaran dapat dicicil dan tidak mengubah kas shift penjualan.</p></div>
    </div>
    @if ($categories->isEmpty())
        <div class="empty-state">Tambahkan kategori dan subkategori terlebih dahulu sebelum mencatat pengeluaran.</div>
    @else
        <form class="pos-order-form" method="POST" action="{{ route('pos.expenses.store') }}">
            @csrf
            <div class="pos-layout" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="field-group">
                    <label class="field-label" for="expense-date">Tanggal</label>
                    <input class="field" id="expense-date" name="expense_date" type="date" value="{{ old('expense_date', today()->toDateString()) }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-reference">Nomor referensi (opsional)</label>
                    <input class="field" id="expense-reference" name="reference_number" value="{{ old('reference_number') }}" maxlength="120">
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-category">Kategori</label>
                    <select class="field" id="expense-category" name="expense_category_id" data-expense-category required>
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-subcategory">Subkategori</label>
                    <select class="field" id="expense-subcategory" name="expense_subcategory_id" data-expense-subcategory required>
                        <option value="">Pilih kategori terlebih dahulu</option>
                        @foreach ($categories as $category)
                            @foreach ($category->subcategories as $subcategory)
                                <option value="{{ $subcategory->id }}" data-category-id="{{ $category->id }}" @selected((string) old('expense_subcategory_id') === (string) $subcategory->id)>{{ $category->name }} · {{ $subcategory->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-amount">Jumlah pengeluaran (Rupiah)</label>
                    <input class="field" id="expense-amount" name="amount" type="number" min="1" max="999999999999" step="1" value="{{ old('amount') }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-description">Keterangan (opsional)</label>
                    <input class="field" id="expense-description" name="description" value="{{ old('description') }}" maxlength="2000">
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-initial-payment">Pembayaran awal (opsional)</label>
                    <input class="field" id="expense-initial-payment" name="initial_payment" type="number" min="0" max="999999999999" step="1" value="{{ old('initial_payment') }}" placeholder="Kosongkan jika belum dibayar">
                </div>
                <div class="field-group">
                    <label class="field-label" for="expense-payment-method">Metode pembayaran awal</label>
                    <select class="field" id="expense-payment-method" name="payment_method">
                        <option value="">Belum ada pembayaran</option>
                        <option value="cash" @selected(old('payment_method') === 'cash')>Tunai</option>
                        <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                    </select>
                </div>
            </div>
            <button class="button" type="submit">Simpan pengeluaran</button>
        </form>
    @endif
</section>

<section class="panel" id="riwayat-pengeluaran">
    <div class="panel-head">
        <div><h2>Riwayat pengeluaran</h2><p>Filter berdasarkan kategori, status pembayaran, dan tanggal.</p></div>
        <span class="status-tag">{{ $expenses->total() }} catatan</span>
    </div>
    <form method="GET" action="{{ route('pos.expenses.index') }}" class="filter-row" style="align-items:end">
        <div style="flex:1 1 190px">
            <label class="field-label" for="expense-search">Cari</label>
            <input class="field" id="expense-search" name="search" value="{{ $filters['search'] }}" placeholder="Kode, referensi, kategori, keterangan">
        </div>
        <div>
            <label class="field-label" for="expense-filter-category">Kategori</label>
            <select class="field" id="expense-filter-category" name="category_id">
                <option value="all" @selected($filters['category_id'] === 'all')>Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label" for="expense-payment-status">Pembayaran</label>
            <select class="field" id="expense-payment-status" name="payment_status">
                <option value="all" @selected($filters['payment_status'] === 'all')>Semua status</option>
                <option value="unpaid" @selected($filters['payment_status'] === 'unpaid')>Belum dibayar</option>
                <option value="partial" @selected($filters['payment_status'] === 'partial')>Dibayar sebagian</option>
                <option value="paid" @selected($filters['payment_status'] === 'paid')>Lunas</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="expense-date-from">Dari tanggal</label>
            <input class="field" id="expense-date-from" name="date_from" type="date" value="{{ $filters['date_from'] }}">
        </div>
        <div>
            <label class="field-label" for="expense-date-to">Sampai tanggal</label>
            <input class="field" id="expense-date-to" name="date_to" type="date" value="{{ $filters['date_to'] }}">
        </div>
        <button class="button" type="submit">Terapkan</button>
        <a class="button button-secondary" href="{{ route('pos.expenses.index') }}">Reset</a>
    </form>

    @if ($expenses->isEmpty())
        <div class="empty-state">Belum ada pengeluaran yang sesuai.</div>
    @else
        @foreach ($expenses as $expense)
            @php
                $isNonCash = $expense->is_non_cash;
                $paid = $isNonCash ? 0 : (int) $expense->payments->sum('amount');
                $remaining = $isNonCash ? 0 : max(0, $expense->amount - $paid);
                $paymentStatus = ['unpaid' => 'Belum dibayar', 'partial' => 'Dibayar sebagian', 'paid' => 'Lunas'][$expense->payment_status] ?? $expense->payment_status;
            @endphp
            <article class="order-card">
                <div class="order-card-head">
                    <div>
                        <h3 class="order-code">{{ $expense->expense_code }}</h3>
                        <p class="order-meta">{{ $expense->expense_date->locale('id')->translatedFormat('d M Y') }} · {{ $expense->category->name }} / {{ $expense->subcategory->name }}</p>
                        <p class="order-meta">{{ $expense->reference_number ? 'Referensi '.$expense->reference_number.' · ' : '' }}Total Rp{{ number_format($expense->amount, 0, ',', '.') }} · {{ $isNonCash ? 'Non-tunai, tidak mengurangi kas' : 'Dibayar Rp'.number_format($paid, 0, ',', '.').' · Sisa Rp'.number_format($remaining, 0, ',', '.') }}</p>
                        @if ($expense->description)<p class="order-meta">{{ $expense->description }}</p>@endif
                    </div>
                    <span class="status-tag {{ $expense->payment_status === 'paid' ? 'paid' : 'pending' }}">{{ $isNonCash ? 'Non-tunai' : $paymentStatus }}</span>
                </div>
                <details class="order-card-details" @if ($errors->any()) open @endif>
                    <summary class="button button-secondary button-small">Detail dan pembayaran</summary>
                @if (! $isNonCash && $expense->payments->isNotEmpty())
                    <ul class="order-lines">
                        @foreach ($expense->payments as $payment)
                            <li>Pembayaran {{ $payment->paid_at->locale('id')->translatedFormat('d M Y') }} · {{ $payment->payment_method === 'cash' ? 'Tunai' : 'Transfer' }} · Rp{{ number_format($payment->amount, 0, ',', '.') }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! $isNonCash && $remaining > 0)
                    <form class="inline-form" method="POST" action="{{ route('pos.expenses.payments.store', $expense) }}">
                        @csrf
                        <label class="visually-hidden" for="expense-payment-date-{{ $expense->id }}">Tanggal pembayaran</label>
                        <input class="field" id="expense-payment-date-{{ $expense->id }}" name="paid_at" type="date" value="{{ today()->toDateString() }}" required>
                        <label class="visually-hidden" for="expense-payment-amount-{{ $expense->id }}">Jumlah pembayaran</label>
                        <input class="field" id="expense-payment-amount-{{ $expense->id }}" name="amount" type="number" min="1" max="{{ $remaining }}" placeholder="Maks. {{ $remaining }}" required>
                        <label class="visually-hidden" for="expense-payment-method-{{ $expense->id }}">Metode pembayaran</label>
                        <select class="field" id="expense-payment-method-{{ $expense->id }}" name="payment_method" required>
                            <option value="cash">Tunai</option>
                            <option value="transfer">Transfer</option>
                        </select>
                        <button class="button button-secondary button-small" type="submit">Catat pembayaran</button>
                    </form>
                @endif
                </details>
            </article>
        @endforeach
        @if ($expenses->hasPages())
            <nav class="pagination" aria-label="Halaman pengeluaran">
                @if ($expenses->previousPageUrl()) <a class="button button-secondary button-small" href="{{ $expenses->previousPageUrl() }}"><x-icon name="chevron-back-outline" /> Sebelumnya</a> @endif
                <span>Halaman {{ $expenses->currentPage() }} dari {{ $expenses->lastPage() }}</span>
                @if ($expenses->nextPageUrl()) <a class="button button-secondary button-small" href="{{ $expenses->nextPageUrl() }}">Berikutnya <x-icon name="chevron-forward-outline" /></a> @endif
            </nav>
        @endif
    @endif
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const category = document.querySelector('[data-expense-category]');
        const subcategory = document.querySelector('[data-expense-subcategory]');
        if (!category || !subcategory) return;

        const filterSubcategories = () => {
            const selectedCategory = category.value;
            let selectedStillValid = false;
            [...subcategory.options].forEach((option) => {
                if (!option.dataset.categoryId) return;
                option.hidden = option.dataset.categoryId !== selectedCategory;
                option.disabled = option.hidden;
                if (!option.disabled && option.selected) selectedStillValid = true;
            });
            if (!selectedStillValid) subcategory.value = '';
            if (selectedCategory && subcategory.options.length > 1) {
                subcategory.options[0].textContent = 'Pilih subkategori';
            }
        };

        category.addEventListener('change', filterSubcategories);
        filterSubcategories();
    });
</script>
@endpush
