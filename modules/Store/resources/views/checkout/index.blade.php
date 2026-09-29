@extends('layouts.app')

@section('title', 'Checkout Pesanan')
@section('subtitle', 'Lengkapi alamat pengiriman dan konfirmasi pembayaran')

@section('content')
<div x-data="{ pin: '', isSubmitting: false }" class="space-y-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.catalog.index') }}" class="hover:text-amber-400">Toko</a>
        <span>/</span>
        <a href="{{ route('store.cart.index') }}" class="hover:text-amber-400">Keranjang</a>
        <span>/</span>
        <span class="text-slate-200">Checkout</span>
    </nav>

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    <form method="POST" action="{{ route('store.checkout.process') }}" @submit="isSubmitting = true">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {{-- Shipping Address Form (7 cols) --}}
            <div class="lg:col-span-7 bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 lg:p-8 space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-700/60">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Alamat Pengiriman & Penerima</h3>
                        <p class="text-xs text-slate-400">Pastikan data pengiriman akurat untuk pengantaran barang atau unit kendaraan.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Nama Penerima</label>
                        <input
                            type="text"
                            name="recipient_name"
                            value="{{ old('recipient_name', auth()->user()->name) }}"
                            required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                            placeholder="Nama Lengkap Penerima"
                        >
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Nomor Telepon / WhatsApp</label>
                        <input
                            type="text"
                            name="phone"
                            value="{{ old('phone', auth()->user()->phone ?? '08123456789') }}"
                            required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                            placeholder="Contoh: 081234567890"
                        >
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-300">Alamat Lengkap</label>
                        <textarea
                            name="address"
                            rows="3"
                            required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 resize-none"
                            placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan"
                        >{{ old('address', 'Jl. Sudirman No. 42, Senayan') }}</textarea>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-300">Kota / Kabupaten</label>
                        <input
                            type="text"
                            name="city"
                            value="{{ old('city', 'Jakarta Selatan') }}"
                            required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                            placeholder="Contoh: Jakarta Selatan"
                        >
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-300">Kode Pos</label>
                        <input
                            type="text"
                            name="postal_code"
                            value="{{ old('postal_code', '12190') }}"
                            required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                            placeholder="Contoh: 12190"
                        >
                    </div>
                </div>

                {{-- Payment PIN Section --}}
                <div class="pt-6 border-t border-slate-700/60 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Autentikasi PIN Dompet</h3>
                            <p class="text-xs text-slate-400">Masukkan 6 digit PIN dompet Anda untuk otorisasi pembayaran langsung.</p>
                        </div>
                    </div>

                    <div class="max-w-xs space-y-1.5">
                        <label class="text-xs font-semibold text-slate-300">6 Digit PIN</label>
                        <input
                            type="password"
                            name="pin"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            inputmode="numeric"
                            required
                            placeholder="••••••"
                            class="w-full tracking-widest text-center px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-xl font-mono focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                        >
                    </div>
                </div>
            </div>

            {{-- Order Summary & Payment Gateway Card (5 cols) --}}
            <div class="lg:col-span-5 space-y-6">
                {{-- Payment Card --}}
                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-6">
                    <h3 class="text-lg font-bold text-white">Ringkasan Pembayaran</h3>

                    {{-- Items Preview --}}
                    <div class="space-y-3 max-h-56 overflow-y-auto pr-1 divide-y divide-slate-700/40">
                        @foreach($cart->items as $item)
                        <div class="pt-2 first:pt-0 flex items-center justify-between gap-3 text-xs">
                            <div class="truncate">
                                <span class="font-bold text-white block truncate">{{ $item->product?->name }}</span>
                                <span class="text-slate-400">{{ $item->qty }} x Rp {{ number_format($item->price_snapshot ?: $item->product->price, 0, ',', '.') }}</span>
                            </div>
                            <span class="font-mono font-bold text-slate-300 whitespace-nowrap">
                                Rp {{ number_format($item->line_total, 0, ',', '.') }}
                            </span>
                        </div>
                        @endforeach
                    </div>

                    {{-- Totals --}}
                    <div class="space-y-2.5 pt-4 border-t border-slate-700/60 text-sm">
                        <div class="flex justify-between text-slate-300">
                            <span>Subtotal Barang</span>
                            <span class="font-mono text-white">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-slate-300">
                            <span>Ongkos Kirim (Flat)</span>
                            <span class="font-mono text-white">Rp {{ number_format($shippingFee, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-slate-700/60">
                            <span class="text-base font-bold text-white">Total Tagihan</span>
                            <span class="text-2xl font-extrabold text-amber-400 font-mono">
                                Rp {{ number_format($grandTotal, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    {{-- Wallet Balance Status --}}
                    <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700/60 space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400">Saldo Dompet Anda:</span>
                            <span class="font-mono font-bold text-white text-sm">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
                        </div>

                        @if($walletBalance >= $grandTotal)
                            <div class="text-[11px] text-emerald-400 flex items-center gap-1.5 font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                Saldo mencukupi untuk pembayaran langsung.
                            </div>
                        @else
                            <div class="text-[11px] text-red-400 flex items-center gap-1.5 font-medium">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                Saldo tidak mencukupi (kurang Rp {{ number_format($grandTotal - $walletBalance, 0, ',', '.') }}).
                            </div>
                            <a
                                href="{{ route('wallet.topup') }}"
                                target="_blank"
                                class="inline-block mt-1 text-xs text-indigo-400 hover:text-indigo-300 underline font-semibold"
                            >
                                + Top Up Saldo Dompet Sekarang &rarr;
                            </a>
                        @endif
                    </div>

                    {{-- Submit Button --}}
                    @if($walletBalance >= $grandTotal)
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="w-full py-4 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-98 text-slate-950 font-extrabold text-sm shadow-xl shadow-amber-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="isSubmitting ? 'Memproses Transaksi...' : 'Konfirmasi & Bayar Sekarang'"></span>
                    </button>
                    @else
                    <button
                        type="button"
                        disabled
                        class="w-full py-4 rounded-2xl bg-slate-700 text-slate-400 font-bold text-sm cursor-not-allowed text-center"
                    >
                        Saldo Tidak Mencukupi
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
