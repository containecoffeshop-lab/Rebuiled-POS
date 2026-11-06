@extends('layouts.app')

@section('title', 'Struk '.$transaction->invoice)

@section('content')
<div class="receipt-page">
    <div class="receipt-actions">
        <button class="button" type="button" onclick="window.print()">Cetak struk</button>
        <a class="button button-secondary" href="{{ route('pos.index', ['module' => 'pesanan']) }}">Kembali ke POS</a>
    </div>
    <article class="receipt-card">
        <header class="receipt-header">
            <h1>{{ $storeSettings['name'] ?? 'Rasa' }}</h1>
            @if (filled($storeSettings['address'] ?? null))<p>{{ $storeSettings['address'] }}</p>@endif
            @if (filled($storeSettings['phone'] ?? null))<p>{{ $storeSettings['phone'] }}</p>@endif
        </header>
        <div class="receipt-meta">
            <div><span>Invoice</span><strong>{{ $transaction->invoice }}</strong></div>
            <div><span>Tanggal</span><strong>{{ $transaction->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</strong></div>
            <div><span>Pelanggan</span><strong>{{ $transaction->customer_name }}</strong></div>
            <div><span>Jenis</span><strong>{{ $transaction->transaction_type === 'dine_in' ? 'Makan di tempat' : 'Dibawa pulang' }}{{ $transaction->diningTable ? ' · Meja '.$transaction->diningTable->number : '' }}</strong></div>
        </div>
        <div class="receipt-items">
            @foreach ($transaction->details as $detail)
                <div class="receipt-item">
                    <div><strong>{{ $detail->item_name }}</strong><span>{{ $detail->quantity }} × Rp{{ number_format($detail->price, 0, ',', '.') }}</span></div>
                    <strong>Rp{{ number_format($detail->line_total, 0, ',', '.') }}</strong>
                </div>
                @if ($detail->promotion_discount > 0)
                    <div class="receipt-note">{{ $detail->promotion_name }} · Diskon Rp{{ number_format($detail->promotion_discount, 0, ',', '.') }}</div>
                @endif
            @endforeach
        </div>
        <div class="receipt-totals">
            <div><span>Subtotal</span><strong>Rp{{ number_format($transaction->subtotal, 0, ',', '.') }}</strong></div>
            @if ($transaction->discount > 0)
                <div><span>Diskon{{ $transaction->coupon_code ? ' · '.$transaction->coupon_code : '' }}</span><strong>−Rp{{ number_format($transaction->discount, 0, ',', '.') }}</strong></div>
            @endif
            @foreach ($transaction->charges as $charge)
                <div><span>{{ $charge->name }} ({{ $charge->code }}) · {{ $charge->rate }}</span><strong>Rp{{ number_format($charge->amount, 0, ',', '.') }}</strong></div>
            @endforeach
            <div class="receipt-grand-total"><span>Total</span><strong>Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</strong></div>
            <div><span>Pembayaran</span><strong>{{ $transaction->payment_method === 'transfer' ? 'Transfer' : 'Tunai' }}{{ $transaction->bankAccount ? ' · '.$transaction->bankAccount->bank_name : '' }}</strong></div>
            <div><span>Uang diterima</span><strong>Rp{{ number_format($transaction->amount_paid, 0, ',', '.') }}</strong></div>
            <div><span>Kembalian</span><strong>Rp{{ number_format($transaction->change_due, 0, ',', '.') }}</strong></div>
        </div>
        @if ($transaction->loyalty_points_earned > 0)
            <p class="receipt-note">Poin loyalitas diperoleh: {{ number_format($transaction->loyalty_points_earned, 0, ',', '.') }}</p>
        @endif
        <footer class="receipt-footer">
            <p>{{ $storeSettings['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda.' }}</p>
            <small>Disimpan pada {{ $transaction->paid_at?->locale('id')->translatedFormat('d M Y, H:i') }}</small>
        </footer>
    </article>
</div>
@endsection

@if (session('print_receipt'))
    @push('scripts')
        <script>
            window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
        </script>
    @endpush
@endif
