@extends('layouts.app')

@section('title', 'Pesanan '.$transaction->invoice)

@section('content')
@php
    $hasKitchenItems = $transaction->details->contains(fn ($detail) => $detail->item_type === 'menu');
    $kitchenLabel = $hasKitchenItems
        ? (['pending' => 'Antre dapur', 'onprogress' => 'Sedang dibuat', 'success' => 'Selesai', 'cancelled' => 'Dibatalkan'][$transaction->kitchen_status] ?? $transaction->kitchen_status)
        : 'Tidak perlu dapur';
@endphp
<div class="auth-wrap">
    <section class="auth-card" style="width:min(100%,650px)">
        <span class="status-tag {{ $transaction->status }}">{{ $transaction->status === 'cancelled' ? 'Pesanan dibatalkan' : 'Pesanan diterima' }}</span>
        <h1>{{ $transaction->status === 'cancelled' ? 'Pesanan dibatalkan.' : 'Terima kasih, '.$transaction->customer_name.'.' }}</h1>
        <p>
            @if ($transaction->status === 'cancelled')
                Hubungi kasir jika Anda merasa ini keliru.
            @else
                Simpan nomor pesanan ini dan tunjukkan ke kasir saat mengambil pesanan. Pembayaran dilakukan di kasir.
            @endif
        </p>
        <span class="track-number">{{ $transaction->invoice }}</span>
        <div class="panel" style="margin:18px 0;padding:18px">
            <div class="order-card-head">
                <div>
                    <strong>{{ $transaction->transaction_type === 'dine_in' ? 'Makan di tempat' : 'Dibawa pulang' }}</strong>
                    <p class="order-meta">{{ $transaction->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</p>
                </div>
                <div style="display:grid;justify-items:end;gap:6px">
                    <span class="status-tag {{ $transaction->status }}">{{ ['pending' => 'Menunggu pembayaran', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan'][$transaction->status] ?? $transaction->status }}</span>
                    <span class="status-tag {{ $hasKitchenItems ? $transaction->kitchen_status : 'paid' }}">{{ $kitchenLabel }}</span>
                </div>
            </div>
            <ul class="order-lines">
                @foreach ($transaction->details as $detail)
                    <li>{{ $detail->quantity }} × {{ $detail->item_name }} <span class="order-meta">({{ $detail->item_type === 'product' ? 'Produk' : 'Menu' }})</span><span style="float:right">Rp{{ number_format($detail->line_total, 0, ',', '.') }}</span></li>
                @endforeach
            </ul>
            @if ($transaction->diningTable)
                <p class="order-meta">Meja {{ $transaction->diningTable->number }}</p>
            @endif
            @if ($transaction->discount > 0 || $transaction->charges->isNotEmpty())
                <div class="cart-total"><span>Subtotal</span><span>Rp{{ number_format($transaction->subtotal, 0, ',', '.') }}</span></div>
                @if ($transaction->promotion_discount > 0)
                    <div class="cart-total"><span>Promo menu</span><span>−Rp{{ number_format($transaction->promotion_discount, 0, ',', '.') }}</span></div>
                @endif
                @if ($transaction->coupon_discount > 0)
                    <div class="cart-total"><span>Kupon{{ $transaction->coupon_code ? ' · '.$transaction->coupon_code : '' }}</span><span>−Rp{{ number_format($transaction->coupon_discount, 0, ',', '.') }}</span></div>
                @endif
                @foreach ($transaction->charges as $charge)
                    <div class="cart-total"><span>{{ $charge->name }} ({{ $charge->code }}) · {{ $charge->rate }}</span><span>Rp{{ number_format($charge->amount, 0, ',', '.') }}</span></div>
                @endforeach
            @endif
            <div class="cart-total"><span>Total bayar di kasir</span><span>Rp{{ number_format($transaction->grand_total, 0, ',', '.') }}</span></div>
            @if ($transaction->loyalty_points_earned > 0)
                <p class="order-meta">Transaksi ini memperoleh {{ number_format($transaction->loyalty_points_earned, 0, ',', '.') }} poin loyalitas.</p>
            @elseif ($transaction->status === 'pending')
                <p class="order-meta">Poin loyalitas, jika aktif, masuk setelah pembayaran dicatat di kasir.</p>
            @endif
        </div>
        @if ($hasKitchenItems && $transaction->status !== 'cancelled' && $transaction->kitchen_status !== 'success')
            <p class="cart-hint" style="margin:12px 0 18px">Status diperbarui saat halaman ini dibuka. <a class="nav-link" href="{{ route('orders.show', $transaction->invoice) }}" style="padding:0;text-decoration:underline">Muat ulang</a></p>
        @endif
        <a class="button button-secondary" href="{{ route('home') }}">Kembali ke menu</a>
    </section>
</div>
@endsection
