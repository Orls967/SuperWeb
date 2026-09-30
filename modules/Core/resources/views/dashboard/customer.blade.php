@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Selamat Datang, ' . auth()->user()->name)

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-card rounded-2xl p-5 hover:border-blue-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Total Booking</p>
                    <p class="text-3xl font-bold text-white mt-1">{{ $stats['total_bookings'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-yellow-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Menunggu</p>
                    <p class="text-3xl font-bold text-yellow-400 mt-1">{{ $stats['pending_bookings'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-cyan-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Kendaraan</p>
                    <p class="text-3xl font-bold text-cyan-400 mt-1">{{ $stats['vehicles_count'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-emerald-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Selesai</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-1">{{ $stats['completed'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Bookings + Vehicles --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Recent Bookings --}}
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                    <div>
                        <h2 class="text-lg font-bold text-white">🔧 Booking Saya</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Riwayat servis terbaru</p>
                    </div>
                    <a href="{{ route('bookings.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 text-white text-sm font-medium hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Booking Baru
                    </a>
                </div>

                @forelse($bookings as $booking)
                <a href="{{ route('bookings.show', $booking) }}" class="flex items-center justify-between px-6 py-4 border-b border-slate-700/30 last:border-b-0 hover:bg-slate-800/50 transition-colors">
                    <div class="flex items-center gap-4">
                        @php
                            $sc = ['pending' => 'bg-yellow-500/10', 'confirmed' => 'bg-blue-500/10', 'in_progress' => 'bg-indigo-500/10', 'completed' => 'bg-emerald-500/10', 'invoiced' => 'bg-slate-500/10'];
                            $tc = ['pending' => 'text-yellow-400', 'confirmed' => 'text-blue-400', 'in_progress' => 'text-indigo-400', 'completed' => 'text-emerald-400', 'invoiced' => 'text-slate-400'];
                        @endphp
                        <div class="w-10 h-10 rounded-xl {{ $sc[$booking->status] ?? 'bg-slate-500/10' }} flex items-center justify-center">
                            @if($booking->status === 'in_progress')
                                <div class="w-2.5 h-2.5 rounded-full bg-indigo-400 pulse-dot"></div>
                            @elseif($booking->status === 'completed' || $booking->status === 'invoiced')
                                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <svg class="w-5 h-5 {{ $tc[$booking->status] ?? 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $booking->service->name }}</p>
                            <p class="text-xs text-slate-400">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }} · {{ $booking->booking_date->format('d M Y') }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-medium border {{ ['pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20', 'confirmed' => 'bg-blue-500/10 text-blue-400 border-blue-500/20', 'in_progress' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20', 'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20', 'invoiced' => 'bg-slate-500/10 text-slate-400 border-slate-500/20'][$booking->status] ?? '' }}">
                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </span>
                        <p class="text-xs text-slate-400 mt-1 font-mono">Rp {{ number_format($booking->grand_total, 0, ',', '.') }}</p>
                    </div>
                </a>
                @empty
                <div class="px-6 py-8 text-center">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm text-slate-400">Belum ada booking</p>
                    <a href="{{ route('bookings.create') }}" class="inline-block mt-2 text-xs text-blue-400 hover:text-blue-300">Buat booking pertama →</a>
                </div>
                @endforelse
            </div>

            {{-- My Vehicles --}}
            @if($vehicles->isNotEmpty())
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                    <h2 class="text-lg font-bold text-white">🚗 Kendaraan Saya</h2>
                    <a href="{{ route('autodex.garage.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Lihat Semua →</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4">
                    @foreach($vehicles as $vehicle)
                    <div class="glass-card rounded-xl p-4 hover:border-cyan-500/30 transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">{{ $vehicle->car?->brand?->name ?? '' }} {{ $vehicle->car?->model ?? '' }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ $vehicle->plate_number }}</p>
                                @if($vehicle->odometer_km)
                                <p class="text-xs text-slate-500 mt-1">{{ number_format($vehicle->odometer_km) }} km</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right Column: Quick Actions + Activity Feed --}}
        <div class="space-y-6">
            {{-- Quick Actions --}}
            <div class="glass-card rounded-2xl p-4">
                <h3 class="text-sm font-bold text-white mb-3">⚡ Aksi Cepat</h3>
                <div class="space-y-2">
                    <a href="{{ route('bookings.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-blue-500/10 border border-blue-500/20 hover:bg-blue-500/20 transition-all text-sm text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Booking Servis
                    </a>
                    <a href="{{ route('store.catalog.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 hover:bg-amber-500/20 transition-all text-sm text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Belanja di Toko
                    </a>
                    <a href="{{ route('autodex.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-violet-500/10 border border-violet-500/20 hover:bg-violet-500/20 transition-all text-sm text-violet-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Jelajahi AutoDex
                    </a>
                    <a href="{{ route('wallet.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 hover:bg-indigo-500/20 transition-all text-sm text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Dompet Saya
                    </a>
                </div>
            </div>

            {{-- Notifications --}}
            @if($unreadNotifications->isNotEmpty())
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/50">
                    <h3 class="text-sm font-bold text-white">🔔 Notifikasi</h3>
                    <a href="{{ route('notifications.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Semua</a>
                </div>
                <div class="divide-y divide-slate-700/30">
                    @foreach($unreadNotifications->take(3) as $notif)
                    <div class="px-4 py-3 {{ $notif->isRead() ? 'opacity-60' : '' }}">
                        <p class="text-xs font-semibold text-white">{{ $notif->title }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 line-clamp-2">{{ $notif->body }}</p>
                        <span class="text-[10px] text-slate-500">{{ $notif->created_at->diffForHumans() }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Activity Feed --}}
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/50">
                    <h3 class="text-sm font-bold text-white">📋 Aktivitas</h3>
                    <a href="{{ route('activity.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Lihat Semua</a>
                </div>
                <div class="divide-y divide-slate-700/30">
                    @forelse($recentActivities->take(5) as $act)
                    <div class="px-4 py-3">
                        <p class="text-xs text-white">{{ $act->description }}</p>
                        <span class="text-[10px] text-slate-500">{{ $act->created_at->diffForHumans() }}</span>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-xs text-slate-500">Belum ada aktivitas</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
