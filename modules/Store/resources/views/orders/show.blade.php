@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->number)
@section('subtitle', 'Status pengiriman dan rincian transaksi belanja')

@section('content')
<div class="space-y-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.catalog.index') }}" class="hover:text-amber-400">Toko</a>
        <span>/</span>
        <a href="{{ route('store.orders.index') }}" class="hover:text-amber-400">Pesanan Saya</a>
        <span>/</span>
        <span class="text-slate-200">{{ $order->number }}</span>
    </nav>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Order Header & Status Card --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 lg:p-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-slate-700/60">
            <div>
                <span class="text-xs text-slate-400">Nomor Pesanan</span>
                <h2 class="text-2xl font-extrabold text-white font-mono">{{ $order->number }}</h2>
                <p class="text-xs text-slate-400 mt-1">Dipesan pada {{ $order->created_at->format('d F Y, H:i:s') }} WIB</p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <span class="px-4 py-1.5 rounded-full text-xs font-bold border {{ $order->status->badgeClasses() }}">
                    {{ $order->status->label() }}
                </span>
                @if($order->paid_at)
                    <span class="text-[11px] text-emerald-400 flex items-center gap-1 font-mono">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Lunas: {{ $order->paid_at->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Status Timeline Tracker --}}
        @php
            $statuses = [
                \Modules\Store\Domain\Enums\OrderStatus::PAID->value => 'Dibayar',
                \Modules\Store\Domain\Enums\OrderStatus::PROCESSING->value => 'Diproses',
                \Modules\Store\Domain\Enums\OrderStatus::SHIPPED->value => 'Dikirim',
                \Modules\Store\Domain\Enums\OrderStatus::COMPLETED->value => 'Selesai',
            ];
            $currentVal = $order->status->value;
            $statusOrder = array_keys($statuses);
            $currentIndex = array_search($currentVal, $statusOrder);
            $isCancelled = in_array($order->status, [\Modules\Store\Domain\Enums\OrderStatus::CANCELLED, \Modules\Store\Domain\Enums\OrderStatus::REFUNDED], true);
        @endphp

        @if($isCancelled)
        <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm flex items-center gap-3">
            <svg class="w-6 h-6 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <strong class="font-bold block">Pesanan ini telah {{ $order->status->label() }}</strong>
                <span class="text-xs text-red-400/80">Alasan: {{ $order->cancellation_reason ?: 'Dibatalkan' }} ({{ $order->cancelled_at?->format('d M Y, H:i') }})</span>
            </div>
        </div>
        @elseif(! $order->isC2c())
        <div class="py-4">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-6">Progres Pesanan</h4>
            <div class="grid grid-cols-4 gap-2 relative">
                @foreach($statuses as $val => $label)
                @php
                    $stepIndex = array_search($val, $statusOrder);
                    $isPassed = $currentIndex !== false && $stepIndex <= $currentIndex;
                    $isCurrent = $val === $currentVal;
                @endphp
                <div class="flex flex-col items-center text-center space-y-2">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-xs transition-all {{ $isPassed ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'bg-slate-700/60 text-slate-400' }}">
                        @if($isPassed)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </div>
                    <span class="text-xs font-semibold {{ $isCurrent ? 'text-amber-400' : ($isPassed ? 'text-white' : 'text-slate-500') }}">
                        {{ $label }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Tracking Number Notice --}}
        @if($order->tracking_number)
        <div class="p-4 rounded-2xl bg-slate-900 border border-purple-500/30 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                </div>
                <div>
                    <span class="text-xs text-slate-400">Nomor Resi Pengiriman:</span>
                    <p class="font-mono font-bold text-white text-base">{{ $order->tracking_number }}</p>
                </div>
            </div>

            @if($order->status === \Modules\Store\Domain\Enums\OrderStatus::SHIPPED)
            <form method="POST" action="{{ route('store.orders.confirm', $order) }}">
                @csrf
                <button
                    type="submit"
                    onclick="return confirm('Apakah Anda yakin ingin menyelesaikan pesanan ini?')"
                    class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow-lg shadow-emerald-500/20 transition-all"
                >
                    Konfirmasi Terima Barang
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>

    @if($order->isC2c())
        @include('store::c2c._escrow-panel', ['order' => $order])
    @endif

    {{-- Order Content: Items & Address (Two Columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Items List (8 cols) --}}
        <div class="lg:col-span-8 bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
            <h3 class="text-base font-bold text-white pb-3 border-b border-slate-700/60">Rincian Barang Pesanan</h3>

            <div class="space-y-3 divide-y divide-slate-700/40">
                @foreach($order->items as $item)
                <div class="pt-3 first:pt-0 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <img
                            src="{{ $item->product?->primary_image }}"
                            alt="{{ $item->name_snapshot }}"
                            class="w-16 h-16 rounded-2xl object-cover bg-slate-900 border border-slate-700 flex-shrink-0"
                        >
                        <div>
                            <h4 class="text-sm font-bold text-white">{{ $item->name_snapshot }}</h4>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ $item->qty }} unit x Rp {{ number_format($item->price_snapshot, 0, ',', '.') }}
                            </p>
                            @if($item->product?->is_car && $order->status === \Modules\Store\Domain\Enums\OrderStatus::COMPLETED)
                            <a href="{{ route('autodex.garage.index') }}" class="inline-flex items-center gap-1 mt-1 text-[11px] text-amber-400 hover:underline">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                Lihat Unit di My Garage &rarr;
                            </a>
                            @endif
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="font-mono font-bold text-amber-400 text-sm">
                            Rp {{ number_format($item->line_total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Summary Totals --}}
            <div class="pt-6 border-t border-slate-700/60 space-y-2 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>Subtotal Produk</span>
                    <span class="font-mono text-white">{{ $order->formatted_subtotal }}</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Biaya Pengiriman</span>
                    <span class="font-mono text-white">{{ $order->formatted_shipping_fee }}</span>
                </div>
                @if($order->discount > 0)
                <div class="flex justify-between text-emerald-400">
                    <span>Diskon</span>
                    <span class="font-mono">-Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                </div>
                @endif
                <div class="flex justify-between items-baseline pt-3 border-t border-slate-700/60 text-sm">
                    <span class="font-bold text-white">Total Tagihan</span>
                    <span class="text-xl font-mono font-extrabold text-amber-400">{{ $order->formatted_grand_total }}</span>
                </div>
            </div>
        </div>

        {{-- Shipping & Action Sidebar (4 cols) --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Shipping Address Card --}}
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                <h4 class="text-sm font-bold text-white">Alamat Pengiriman</h4>
                <div class="text-xs text-slate-300 space-y-1.5 leading-relaxed bg-slate-900/60 p-4 rounded-2xl border border-slate-700/60">
                    <p class="font-bold text-white">{{ $order->shipping_address['name'] ?? auth()->user()->name }}</p>
                    <p class="text-slate-400">{{ $order->shipping_address['phone'] ?? '-' }}</p>
                    <p class="pt-1">{{ $order->shipping_address['address'] ?? '-' }}</p>
                    <p>{{ $order->shipping_address['city'] ?? '-' }}, {{ $order->shipping_address['postal_code'] ?? '-' }}</p>
                </div>
            </div>

            {{-- Cancellation / Refund Action --}}
            @if(in_array($order->status, [\Modules\Store\Domain\Enums\OrderStatus::PAID, \Modules\Store\Domain\Enums\OrderStatus::PROCESSING], true))
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-3">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Batalkan Pesanan</h4>
                <p class="text-xs text-slate-400">Pesanan belum dikirim. Pembatalan otomatis akan mengembalikan saldo ke dompet Anda dan memulihkan stok.</p>
                <form method="POST" action="{{ route('store.orders.cancel', $order) }}">
                    @csrf
                    <input type="hidden" name="reason" value="Dibatalkan oleh pelanggan sebelum pengiriman">
                    <button
                        type="submit"
                        onclick="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini? Dana akan otomatis direfund ke dompet Anda.')"
                        class="w-full py-2.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 font-bold text-xs transition-all"
                    >
                        Batalkan & Refund Pesanan
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
