@extends('layouts.app')

@section('title', 'Mobil Bekas Antar Pengguna')
@section('subtitle', 'Beli mobil bekas dengan dana ditahan escrow dan riwayat servis terverifikasi')

@section('content')
<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <nav class="flex items-center gap-2 text-xs text-slate-400">
            <a href="{{ route('store.catalog.index') }}" class="hover:text-amber-400">Toko</a>
            <span>/</span>
            <span class="text-slate-200">Mobil Bekas (C2C)</span>
        </nav>

        @auth
        <a href="{{ route('store.c2c.mySales') }}"
            class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition-all">
            Jual Mobilku
        </a>
        @endauth
    </div>

    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    <div class="p-5 rounded-3xl bg-indigo-500/5 border border-indigo-500/20 flex items-start gap-4">
        <div class="w-11 h-11 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <div class="text-xs text-slate-300 leading-relaxed">
            <strong class="text-white block mb-1">Transaksi aman dengan escrow</strong>
            Dana kamu ditahan platform sampai kendaraan benar-benar diserahkan dan kamu konfirmasi terima.
            Setiap unit membawa Paspor Digital berbasis hash-chain: riwayat servis dan penggantian sparepart tidak bisa dipalsukan.
        </div>
    </div>

    @if($listings->isEmpty())
    <div class="py-20 text-center space-y-3">
        <p class="text-slate-400 text-sm">Belum ada mobil bekas yang dijual pengguna lain saat ini.</p>
        @auth
        <a href="{{ route('store.c2c.mySales') }}" class="text-amber-400 text-sm font-semibold hover:underline">Jadilah penjual pertama &rarr;</a>
        @endauth
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($listings as $listing)
        @php $vehicle = $listing->productable; @endphp
        <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden flex flex-col hover:border-amber-500/40 transition-all">
            <img src="{{ $listing->primary_image }}" alt="{{ $listing->name }}" class="w-full h-44 object-cover bg-slate-900">

            <div class="p-5 space-y-3 flex-1 flex flex-col">
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-white leading-snug">{{ $listing->name }}</h3>
                    <p class="text-xs text-slate-400">Dijual oleh {{ $listing->seller?->name ?? 'Pengguna' }}</p>
                </div>

                @if($vehicle)
                <div class="flex flex-wrap gap-2 text-[11px]">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 text-slate-300 font-mono">
                        {{ number_format((int) $vehicle->odometer_km, 0, ',', '.') }} km
                    </span>
                    @if($vehicle->color)
                    <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 text-slate-300">{{ $vehicle->color }}</span>
                    @endif
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                        {{ $vehicle->events()->count() }} blok paspor
                    </span>
                </div>
                @endif

                <p class="text-xs text-slate-400 line-clamp-3 flex-1">{{ $listing->description }}</p>

                <div class="pt-3 border-t border-slate-700/60 space-y-3">
                    <span class="text-xl font-mono font-extrabold text-amber-400 block">{{ $listing->formatted_price }}</span>

                    <div class="flex gap-2">
                        @auth
                            @if((int) $listing->seller_id === (int) auth()->id())
                            <span class="flex-1 text-center py-2.5 rounded-xl bg-slate-700/40 text-slate-400 font-bold text-xs">Listing Milikmu</span>
                            @else
                            <a href="{{ route('store.c2c.buyForm', $listing) }}"
                                class="flex-1 text-center py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-all">
                                Beli via Escrow
                            </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="flex-1 text-center py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-all">
                                Masuk untuk Membeli
                            </a>
                        @endauth

                        @if($vehicle)
                        <a href="{{ $vehicle->getPassportUrl() }}" target="_blank"
                            class="px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-slate-300 hover:text-amber-400 font-bold text-xs transition-all">
                            Paspor
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div>{{ $listings->links() }}</div>
    @endif
</div>
@endsection
