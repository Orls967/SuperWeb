@extends('layouts.app')

@section('title', 'Dashboard Mekanik')
@section('subtitle', 'Job Queue & Servis Aktif')

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
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center group-hover:bg-blue-500/20 transition-colors">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-yellow-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Pending</p>
                    <p class="text-3xl font-bold text-yellow-400 mt-1">{{ $stats['pending_bookings'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center group-hover:bg-yellow-500/20 transition-colors">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-indigo-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Job Saya Aktif</p>
                    <p class="text-3xl font-bold text-indigo-400 mt-1">{{ $stats['my_active'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center group-hover:bg-indigo-500/20 transition-colors">
                    <div class="w-3 h-3 rounded-full bg-indigo-400 pulse-dot"></div>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-emerald-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Selesai Hari Ini</p>
                    <p class="text-3xl font-bold text-emerald-400 mt-1">{{ $stats['my_completed_today'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20 transition-colors">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    @if($stats['low_stock_parts'] > 0)
    <div class="glass-card rounded-2xl p-4 border-amber-500/20 bg-amber-500/5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-amber-400">Peringatan Stok Rendah</p>
                <p class="text-xs text-slate-400">{{ $stats['low_stock_parts'] }} sparepart memiliki stok ≤ 5 unit</p>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- My Active Jobs --}}
        <div class="lg:col-span-2 space-y-6">
            @if($myBookings->isNotEmpty())
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/50">
                    <h2 class="text-lg font-bold text-white">🔧 Job Aktif Saya</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Booking yang sedang Anda kerjakan</p>
                </div>
                <div class="divide-y divide-slate-700/30">
                    @foreach($myBookings as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" class="flex items-center justify-between px-6 py-4 hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                                <div class="w-2.5 h-2.5 rounded-full bg-indigo-400 pulse-dot"></div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                                <p class="text-xs text-slate-400">{{ $booking->service->name }} · {{ $booking->customer->name }}</p>
                            </div>
                        </div>
                        <span class="font-mono text-xs font-bold text-blue-400">{{ $booking->booking_code }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Pending Queue --}}
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                    <div>
                        <h2 class="text-lg font-bold text-white">📋 Antrian Pending</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Booking menunggu dikerjakan</p>
                    </div>
                    <a href="{{ route('bookings.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 text-white text-sm font-medium hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Booking Baru
                    </a>
                </div>
                @forelse($pendingBookings as $booking)
                <a href="{{ route('bookings.show', $booking) }}" class="flex items-center justify-between px-6 py-3 border-b border-slate-700/30 last:border-b-0 hover:bg-slate-800/50 transition-colors">
                    <div>
                        <p class="text-sm text-white">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }} — {{ $booking->service->name }}</p>
                        <p class="text-xs text-slate-400">{{ $booking->customer->name }} · {{ $booking->booking_date->format('d M Y') }}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-medium border bg-yellow-500/10 text-yellow-400 border-yellow-500/20">Pending</span>
                </a>
                @empty
                <div class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada booking pending 🎉</div>
                @endforelse
            </div>
        </div>

        {{-- Activity Feed --}}
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <h3 class="text-sm font-bold text-white">Aktivitas Bengkel</h3>
                <a href="{{ route('activity.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Lihat Semua</a>
            </div>
            <div class="divide-y divide-slate-700/30">
                @forelse($recentActivities as $act)
                <div class="px-4 py-3">
                    <p class="text-xs text-white">{{ $act->description }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] text-slate-500">{{ $act->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-slate-500">Belum ada aktivitas</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
