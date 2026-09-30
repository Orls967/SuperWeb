@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('subtitle', 'Overview Seluruh Platform')

@section('content')
<div class="space-y-6">

    {{-- ============================================================ --}}
    {{-- PLATFORM-WIDE STATS --}}
    {{-- ============================================================ --}}
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
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">In Progress</p>
                    <p class="text-3xl font-bold text-indigo-400 mt-1">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center group-hover:bg-indigo-500/20 transition-colors">
                    <div class="w-3 h-3 rounded-full bg-indigo-400 pulse-dot"></div>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl p-5 hover:border-emerald-500/30 transition-all group">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-400 mt-1">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center group-hover:bg-emerald-500/20 transition-colors">
                    <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Secondary Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-card rounded-2xl p-4 hover:border-violet-500/30 transition-all">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Users</p>
                    <p class="text-lg font-bold text-white">{{ $stats['total_users'] }}</p>
                </div>
            </div>
        </div>
        <div class="glass-card rounded-2xl p-4 hover:border-cyan-500/30 transition-all">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Kendaraan</p>
                    <p class="text-lg font-bold text-white">{{ $stats['total_vehicles'] }}</p>
                </div>
            </div>
        </div>
        <div class="glass-card rounded-2xl p-4 hover:border-amber-500/30 transition-all">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Pesanan Toko</p>
                    <p class="text-lg font-bold text-white">{{ $stats['total_orders'] }}</p>
                </div>
            </div>
        </div>
        <div class="glass-card rounded-2xl p-4 hover:border-amber-500/30 transition-all">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Stok Rendah</p>
                    <p class="text-lg font-bold {{ $stats['low_stock_parts'] > 0 ? 'text-amber-400' : 'text-white' }}">{{ $stats['low_stock_parts'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Low Stock Alert --}}
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
        {{-- Recent Bookings --}}
        <div class="lg:col-span-2 glass-card rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <div>
                    <h2 class="text-lg font-bold text-white">Booking Terbaru</h2>
                    <p class="text-xs text-slate-400 mt-0.5">10 booking terakhir</p>
                </div>
                <a href="{{ route('bookings.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 text-white text-sm font-medium hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Booking Baru
                </a>
            </div>

            @if($bookings->isEmpty())
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-slate-400">Belum ada booking.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Servis</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            @foreach($bookings as $booking)
                            <tr class="hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-3"><span class="font-mono text-xs font-bold text-blue-400">{{ $booking->booking_code }}</span></td>
                                <td class="px-6 py-3">
                                    <p class="font-medium text-white text-xs">{{ $booking->customer->name }}</p>
                                </td>
                                <td class="px-6 py-3 text-slate-300 text-xs">{{ $booking->service->name }}</td>
                                <td class="px-6 py-3">
                                    @php
                                        $sc = ['pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20', 'confirmed' => 'bg-blue-500/10 text-blue-400 border-blue-500/20', 'in_progress' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20', 'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20', 'invoiced' => 'bg-slate-500/10 text-slate-400 border-slate-500/20'];
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-medium border {{ $sc[$booking->status] ?? '' }}">
                                        @if($booking->status === 'in_progress')<span class="w-1.5 h-1.5 rounded-full bg-indigo-400 pulse-dot"></span>@endif
                                        {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('bookings.show', $booking) }}" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-400 hover:bg-blue-500/10 transition-all" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Activity Feed Sidebar --}}
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <h3 class="text-sm font-bold text-white">Aktivitas Terbaru</h3>
                <a href="{{ route('activity.index') }}" class="text-xs text-blue-400 hover:text-blue-300">Lihat Semua</a>
            </div>
            <div class="divide-y divide-slate-700/30">
                @forelse($recentActivities as $act)
                <div class="px-4 py-3">
                    @php
                        $mc = ['autoserve' => 'text-blue-400', 'banking' => 'text-indigo-400', 'payment' => 'text-emerald-400', 'store' => 'text-amber-400', 'crypto' => 'text-orange-400'];
                    @endphp
                    <p class="text-xs text-white">{{ $act->description }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-bold uppercase {{ $mc[$act->module] ?? 'text-slate-400' }}">{{ $act->module }}</span>
                        <span class="text-slate-600">·</span>
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
