@extends('layouts.app')

@section('title', $car->full_name)
@section('subtitle', 'Detail Spesifikasi Kendaraan')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    
    <div class="flex items-center gap-4 mb-2">
        <a href="{{ route('autodex.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <h2 class="text-2xl font-black text-white">{{ $car->brand->name }} {{ $car->model }}</h2>
    </div>

    <div class="glass-card rounded-2xl overflow-hidden">
        <!-- Hero Header -->
        <div class="h-48 bg-slate-800 relative flex items-center justify-center">
            <span class="text-6xl font-black text-slate-700/30 uppercase tracking-widest absolute">{{ $car->brand->name }}</span>
            <div class="relative z-10 text-center">
                <span class="px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider {{ $car->fuel_badge }} shadow-lg backdrop-blur-sm">
                    {{ $car->fuel_type }}
                </span>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 mb-8">
                <div>
                    <h3 class="text-3xl font-black text-white leading-tight mb-2">{{ $car->full_name }}</h3>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-2xl">{{ $car->description }}</p>
                </div>
                <div class="text-left sm:text-right shrink-0">
                    <p class="text-xs text-slate-400 uppercase tracking-wider font-bold mb-1">Estimasi Harga</p>
                    <p class="text-2xl font-black text-emerald-400">{{ $car->formatted_price }}</p>
                </div>
            </div>

            <!-- Specs Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Body Type</p>
                    <p class="font-bold text-white">{{ $car->body_type ?? '-' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Mesin</p>
                    <p class="font-bold text-white text-sm truncate" title="{{ $car->engine }}">{{ $car->engine ?? '-' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Transmisi</p>
                    <p class="font-bold text-white text-sm truncate">{{ $car->transmission ?? '-' }}</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Penggerak</p>
                    <p class="font-bold text-white">{{ $car->drivetrain ?? '-' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Performance -->
                <div class="border border-slate-700/50 rounded-xl overflow-hidden">
                    <div class="bg-slate-800/50 px-4 py-3 border-b border-slate-700/50">
                        <h4 class="font-bold text-white text-sm">Performa</h4>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex justify-between items-center border-b border-slate-700/30 pb-2">
                            <span class="text-sm text-slate-400">Tenaga (Horsepower)</span>
                            <span class="font-bold text-white">{{ $car->horsepower ?? '-' }} HP</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-700/30 pb-2">
                            <span class="text-sm text-slate-400">Torsi</span>
                            <span class="font-bold text-white">{{ $car->torque_nm ?? '-' }} Nm</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-700/30 pb-2">
                            <span class="text-sm text-slate-400">Top Speed</span>
                            <span class="font-bold text-white">{{ $car->top_speed_kmh ?? '-' }} km/h</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-400">0-100 km/h</span>
                            <span class="font-bold text-white">{{ $car->zero_to_100 ?? '-' }} detik</span>
                        </div>
                    </div>
                </div>

                <!-- EV Specific -->
                @if($car->isElectric())
                <div class="border border-emerald-500/30 rounded-xl overflow-hidden relative">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="bg-emerald-500/10 px-4 py-3 border-b border-emerald-500/20">
                        <h4 class="font-bold text-emerald-400 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Spesifikasi EV
                        </h4>
                    </div>
                    <div class="p-4 space-y-4 relative z-10">
                        <div class="flex justify-between items-center border-b border-slate-700/30 pb-2">
                            <span class="text-sm text-slate-400">Kapasitas Baterai</span>
                            <span class="font-bold text-white">{{ $car->battery_kwh ?? '-' }} kWh</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-400">Jarak Tempuh (Range)</span>
                            <span class="font-bold text-emerald-400">{{ $car->range_km ?? '-' }} km</span>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Actions -->
            @auth
            <div class="mt-8 pt-6 border-t border-slate-700/50 flex flex-wrap gap-4">
                <form action="{{ route('autodex.garage.toggle', $car) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-6 py-3 rounded-xl font-medium transition-all shadow-lg flex items-center gap-2
                        {{ auth()->user()->hasInGarage($car->id) ? 'bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500 hover:text-white' : 'bg-blue-500 text-white hover:bg-blue-600 shadow-blue-500/25' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                        {{ auth()->user()->hasInGarage($car->id) ? 'Keluarkan dari Garasi' : 'Tambah ke My Garage' }}
                    </button>
                </form>
                
                @if(!auth()->user()->hasInGarage($car->id))
                <form action="{{ route('autodex.wishlist.toggle', $car) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-6 py-3 rounded-xl font-medium transition-all border flex items-center gap-2
                        {{ auth()->user()->hasInWishlist($car->id) ? 'bg-pink-500/10 text-pink-400 border-pink-500/30' : 'bg-slate-800 text-slate-400 border-slate-700 hover:text-white hover:border-slate-500' }}">
                        <svg class="w-5 h-5" fill="{{ auth()->user()->hasInWishlist($car->id) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        {{ auth()->user()->hasInWishlist($car->id) ? 'Hapus dari Wishlist' : 'Tambah ke Wishlist' }}
                    </button>
                </form>
                @endif

                @php
                    $storeProduct = \Modules\Store\Domain\Models\Product::where('productable_type', 'dex_car')
                        ->where('productable_id', $car->id)
                        ->where('is_listed', true)
                        ->where('cached_stock', '>', 0)
                        ->first();
                @endphp

                @if($storeProduct)
                <a href="{{ route('store.products.show', $storeProduct->slug) }}" class="px-6 py-3 rounded-xl font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition-all shadow-lg shadow-amber-500/25 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Beli di Store ({{ $storeProduct->formatted_price }})
                </a>
                @endif
            </div>
            @endauth
        </div>
    </div>
</div>
@endsection
