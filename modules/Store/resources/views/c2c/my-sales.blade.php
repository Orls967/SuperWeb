@extends('layouts.app')

@section('title', 'Penjualan Mobil Saya')
@section('subtitle', 'Pasang mobil di Store dan pantau transaksi escrow pembeli')

@section('content')
<div class="space-y-8">
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.c2c.index') }}" class="hover:text-amber-400">Mobil Bekas</a>
        <span>/</span>
        <span class="text-slate-200">Penjualan Saya</span>
    </nav>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Form pasang listing --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 lg:p-8">
        <h3 class="text-base font-bold text-white mb-1">Pasang Mobil untuk Dijual</h3>
        <p class="text-xs text-slate-400 mb-6">
            Pembeli membayar ke escrow. Setelah kendaraan diserahkan dan dikonfirmasi, dana masuk ke dompetmu
            dikurangi fee platform {{ \Modules\Store\Domain\Models\Order::C2C_PLATFORM_FEE_PERCENT }}%.
        </p>

        @if($sellableVehicles->isEmpty())
        <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/60 text-xs text-slate-400">
            Tidak ada kendaraan aktif di garasimu yang bisa dipasang.
            <a href="{{ route('autodex.garage.index') }}" class="text-amber-400 hover:underline">Buka My Garage &rarr;</a>
        </div>
        @else
        <form method="POST" action="{{ route('store.c2c.store') }}"
            x-data="{ price: {{ old('price', 0) }}, get sellerNet() { return Math.round(this.price * {{ (100 - \Modules\Store\Domain\Models\Order::C2C_PLATFORM_FEE_PERCENT) / 100 }}); }, submitting: false }"
            @submit="submitting = true"
            class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @csrf

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-300">Pilih Kendaraan</label>
                <select name="vehicle_id" required
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500">
                    <option value="">— Pilih dari garasimu —</option>
                    @foreach($sellableVehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>
                        {{ $vehicle->car?->brand?->name }} {{ $vehicle->car?->model }}
                        @if($vehicle->plate_number) — {{ $vehicle->plate_number }} @endif
                        ({{ number_format((int) $vehicle->odometer_km, 0, ',', '.') }} km)
                    </option>
                    @endforeach
                </select>
                @error('vehicle_id')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-300">Harga Jual (Rp)</label>
                <input type="number" name="price" x-model.number="price" min="1000000" step="100000" required
                    value="{{ old('price') }}"
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm font-mono focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                    placeholder="150000000">
                <p class="text-[11px] text-slate-400" x-show="price >= 1000000" x-cloak>
                    Kamu menerima sekitar
                    <span class="font-mono text-emerald-400" x-text="'Rp ' + sellerNet.toLocaleString('id-ID')"></span>
                    setelah fee platform.
                </p>
                @error('price')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="space-y-1.5 lg:col-span-2">
                <label class="text-xs font-semibold text-slate-300">Deskripsi (opsional)</label>
                <textarea name="description" rows="3" maxlength="2000"
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-2xl text-white text-sm focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                    placeholder="Ceritakan kondisi kendaraan, kelengkapan surat, dan riwayat perawatan.">{{ old('description') }}</textarea>
                @error('description')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <button type="submit" :disabled="submitting"
                    class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition-all">
                    <span x-show="! submitting">Pasang di Store</span>
                    <span x-show="submitting" x-cloak>Memproses…</span>
                </button>
            </div>
        </form>
        @endif
    </div>

    {{-- Listing aktif --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
        <h3 class="text-base font-bold text-white pb-3 border-b border-slate-700/60">Listing Saya</h3>

        @forelse($listings as $listing)
        <div class="flex flex-wrap items-center justify-between gap-4 py-3 border-b border-slate-700/40 last:border-0">
            <div>
                <h4 class="text-sm font-bold text-white">{{ $listing->name }}</h4>
                <p class="text-xs text-slate-400 font-mono">{{ $listing->formatted_price }} · SKU {{ $listing->sku }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $listing->is_listed ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20' }}">
                    {{ $listing->is_listed ? 'Aktif' : 'Nonaktif' }}
                </span>
                @if($listing->is_listed && $listing->cached_stock > 0)
                <form method="POST" action="{{ route('store.c2c.unlist', $listing) }}">
                    @csrf
                    <button type="submit" onclick="return confirm('Tarik listing ini dari Store?')"
                        class="px-4 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 font-bold text-[11px] transition-all">
                        Tarik Listing
                    </button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <p class="text-xs text-slate-400 py-4">Belum ada listing yang kamu pasang.</p>
        @endforelse
    </div>

    {{-- Transaksi masuk --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
        <h3 class="text-base font-bold text-white pb-3 border-b border-slate-700/60">Transaksi Pembeli</h3>

        @forelse($sales as $sale)
        <div class="flex flex-wrap items-center justify-between gap-4 py-3 border-b border-slate-700/40 last:border-0">
            <div>
                <h4 class="text-sm font-bold text-white font-mono">{{ $sale->number }}</h4>
                <p class="text-xs text-slate-400">
                    Pembeli: {{ $sale->user?->name }} · {{ $sale->created_at->format('d M Y, H:i') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-mono font-bold text-amber-400 text-sm">{{ $sale->formatted_grand_total }}</span>
                <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $sale->status->badgeClasses() }}">
                    {{ $sale->status->label() }}
                </span>
                <a href="{{ route('store.orders.show', $sale) }}" class="text-xs text-amber-400 hover:underline">Detail</a>
            </div>
        </div>
        @empty
        <p class="text-xs text-slate-400 py-4">Belum ada pembeli untuk listingmu.</p>
        @endforelse
    </div>
</div>
@endsection
