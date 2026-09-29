@extends('layouts.app')

@section('title', 'My Garage & Wishlist')
@section('subtitle', 'Kelola koleksi mobil Anda')

@section('content')
<div class="space-y-8">
    
    <!-- Notifikasi -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 font-medium text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <a href="{{ route('autodex.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Katalog
        </a>
    </div>

    <!-- MY GARAGE SECTION -->
    <div>
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-white">My Garage ({{ $garageCars->count() }})</h2>
        </div>

        @if($garageCars->isEmpty())
            <div class="glass-card rounded-2xl p-8 text-center border-dashed border-2 border-slate-700/50">
                <p class="text-slate-400 mb-4">Garasi Anda masih kosong.</p>
                <a href="{{ route('autodex.index') }}" class="inline-flex px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-medium transition-all">
                    Cari Mobil di Katalog
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($garageCars as $car)
                <div class="glass-card rounded-2xl p-5 border border-blue-500/20 relative group">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-xs font-medium text-slate-400 mb-0.5">{{ $car->brand->name }}</p>
                            <h3 class="text-lg font-bold text-white leading-tight">{{ $car->model }}</h3>
                            <p class="text-[10px] text-blue-400 mt-1 uppercase tracking-wider font-bold">MILIK ANDA</p>
                        </div>
                        <form action="{{ route('autodex.garage.toggle', $car) }}" method="POST">
                            @csrf
                            <button type="submit" class="p-2 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white transition-all opacity-0 group-hover:opacity-100" title="Keluarkan dari Garasi" onclick="return confirm('Keluarkan mobil ini dari garasi?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                    
                    <div class="bg-slate-900/50 rounded-xl p-3 text-sm text-slate-400">
                        Ditambahkan: {{ $car->pivot->created_at->format('d M Y') }}
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- WISHLIST SECTION -->
    <div class="pt-8 border-t border-slate-700/50">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-pink-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-white">Wishlist ({{ $wishlistCars->count() }})</h2>
        </div>

        @if($wishlistCars->isEmpty())
            <div class="glass-card rounded-2xl p-8 text-center border-dashed border-2 border-slate-700/50">
                <p class="text-slate-400">Belum ada mobil impian di wishlist.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($wishlistCars as $car)
                <div class="glass-card rounded-2xl p-4 relative group">
                    <p class="text-[10px] font-medium text-slate-400 mb-0.5">{{ $car->brand->name }}</p>
                    <h3 class="text-base font-bold text-white leading-tight mb-3 truncate">{{ $car->model }}</h3>
                    
                    <div class="flex gap-2">
                        <!-- Pindah ke garasi -->
                        <form action="{{ route('autodex.garage.toggle', $car) }}" method="POST" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full py-2 rounded-lg bg-blue-500/10 text-blue-400 text-xs font-medium hover:bg-blue-500 hover:text-white transition-all">
                                Pindah ke Garasi
                            </button>
                        </form>
                        
                        <!-- Hapus dari wishlist -->
                        <form action="{{ route('autodex.wishlist.toggle', $car) }}" method="POST">
                            @csrf
                            <button type="submit" class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:bg-red-500 hover:text-white transition-all" title="Hapus Wishlist">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
