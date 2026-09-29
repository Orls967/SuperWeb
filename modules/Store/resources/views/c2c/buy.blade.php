@extends('layouts.app')

@section('title', 'Beli ' . $product->name)
@section('subtitle', 'Pembayaran ditahan escrow sampai kamu konfirmasi kendaraan diterima')

@section('content')
@php
    $sellerNet = $product->price - intdiv($product->price * $platformFeePercent, 100);
@endphp

<div x-data="{
        pin: '',
        submitting: false,
        walletBalance: {{ $walletBalance }},
        price: {{ $product->price }},
        idempotencyKey: crypto.randomUUID(),
        get balanceAfter() { return this.walletBalance - this.price; },
        get isAffordable() { return this.balanceAfter >= 0; }
    }" class="space-y-8">

    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.c2c.index') }}" class="hover:text-amber-400">Mobil Bekas</a>
        <span>/</span>
        <span class="text-slate-200">{{ $product->name }}</span>
    </nav>

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('store.c2c.buy', $product) }}" @submit="submitting = true">
        @csrf
        <input type="hidden" name="idempotency_key" :value="idempotencyKey">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            {{-- Kendaraan & alamat --}}
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden">
                    <img src="{{ $product->primary_image }}" alt="{{ $product->name }}" class="w-full h-56 object-cover bg-slate-900">
                    <div class="p-6 space-y-3">
                        <h2 class="text-xl font-extrabold text-white">{{ $product->name }}</h2>
                        <p class="text-xs text-slate-400">Dijual oleh {{ $product->seller?->name }}</p>

                        @if($vehicle)
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs pt-3 border-t border-slate-700/60">
                            <div>
                                <span class="text-slate-400 block">Odometer</span>
                                <span class="font-mono text-white">{{ number_format((int) $vehicle->odometer_km, 0, ',', '.') }} km</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Warna</span>
                                <span class="text-white">{{ $vehicle->color ?: '-' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block">Plat</span>
                                <span class="font-mono text-white">{{ $vehicle->plate_number ?: '-' }}</span>
                            </div>
                        </div>

                        <a href="{{ $vehicle->getPassportUrl() }}" target="_blank"
                            class="inline-flex items-center gap-2 text-xs text-amber-400 hover:underline">
                            Verifikasi Paspor Digital kendaraan ini &rarr;
                        </a>
                        @endif

                        <p class="text-xs text-slate-400 leading-relaxed pt-2">{{ $product->description }}</p>
                    </div>
                </div>

                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-5">
                    <h3 class="text-base font-bold text-white">Data Penerima & Lokasi Serah Terima</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="text-xs font-semibold text-slate-300">Nama Penerima</label>
                            <input type="text" name="recipient_name" required value="{{ old('recipient_name', auth()->user()->name) }}"
                                class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            @error('recipient_name')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="text-xs font-semibold text-slate-300">Nomor Telepon</label>
                            <input type="text" name="phone" required value="{{ old('phone') }}"
                                class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                                placeholder="08123456789">
                            @error('phone')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="text-xs font-semibold text-slate-300">Alamat Serah Terima</label>
                            <textarea name="address" rows="2" required
                                class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">{{ old('address') }}</textarea>
                            @error('address')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-300">Kota</label>
                            <input type="text" name="city" required value="{{ old('city') }}"
                                class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            @error('city')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-300">Kode Pos</label>
                            <input type="text" name="postal_code" required value="{{ old('postal_code') }}"
                                class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm font-mono focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                            @error('postal_code')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ringkasan escrow & PIN --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-indigo-500/30 p-6 space-y-4">
                    <h3 class="text-base font-bold text-white">Ringkasan Escrow</h3>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Harga Kendaraan</span>
                            <span class="font-mono text-white">{{ $product->formatted_price }}</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Diterima Penjual ({{ 100 - $platformFeePercent }}%)</span>
                            <span class="font-mono text-emerald-400">Rp {{ number_format($sellerNet, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Fee Platform ({{ $platformFeePercent }}%)</span>
                            <span class="font-mono text-amber-400">Rp {{ number_format($product->price - $sellerNet, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-slate-700/60 text-sm">
                            <span class="font-bold text-white">Total Ditahan</span>
                            <span class="text-xl font-mono font-extrabold text-amber-400">{{ $product->formatted_price }}</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/60 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Saldo Dompet</span>
                            <span class="font-mono text-white">Rp {{ number_format($walletBalance, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Saldo Setelah Bayar</span>
                            <span class="font-mono" :class="isAffordable ? 'text-emerald-400' : 'text-red-400'"
                                x-text="'Rp ' + balanceAfter.toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <template x-if="! isAffordable">
                        <div class="p-3 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
                            Saldo tidak mencukupi.
                            <a href="{{ route('wallet.index') }}" class="underline font-semibold">Top Up dompet dulu &rarr;</a>
                        </div>
                    </template>

                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Dana tersimpan di escrow platform. Setelah penjual menyerahkan kendaraan, kamu punya
                        {{ $autoCaptureDays }} hari untuk konfirmasi. Lewat batas itu dana otomatis cair ke penjual.
                        Ada masalah? Ajukan sengketa dan admin yang memutuskan.
                    </p>
                </div>

                <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
                    <label class="text-xs font-semibold text-slate-300 block">PIN Dompet (6 digit)</label>
                    <input type="password" name="pin" x-model="pin" inputmode="numeric" maxlength="6" required
                        class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-center text-2xl font-mono tracking-[0.5em] focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                        placeholder="••••••">
                    @error('pin')<p class="text-xs text-red-400">{{ $message }}</p>@enderror

                    <button type="submit" :disabled="submitting || pin.length !== 6 || ! isAffordable"
                        class="w-full py-3.5 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all">
                        <span x-show="! submitting">Tahan Dana di Escrow & Beli</span>
                        <span x-show="submitting" x-cloak>Memproses…</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
