@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', auth()->user()->isStaff() ? 'Overview Bengkel' : 'Selamat Datang, ' . auth()->user()->name)

@section('content')
<div class="space-y-6">

    {{-- ============================================================ --}}
    {{-- STATS CARDS --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @if(auth()->user()->isStaff())
            {{-- Admin/Mekanik Stats --}}
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
        @else
            {{-- Customer Stats --}}
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

            <div class="glass-card rounded-2xl p-5 hover:border-indigo-500/30 transition-all group">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Dikerjakan</p>
                        <p class="text-3xl font-bold text-indigo-400 mt-1">{{ $stats['in_progress'] }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center">
                        <div class="w-3 h-3 rounded-full bg-indigo-400 pulse-dot"></div>
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
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- LOW STOCK ALERT (Admin/Mekanik only) --}}
    {{-- ============================================================ --}}
    @if(auth()->user()->isStaff() && $stats['low_stock_parts'] > 0)
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

    {{-- ============================================================ --}}
    {{-- BOOKING LIST TABLE --}}
    {{-- ============================================================ --}}
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
            <div>
                <h2 class="text-lg font-bold text-white">Daftar Booking</h2>
                <p class="text-xs text-slate-400 mt-0.5">{{ auth()->user()->isStaff() ? 'Semua job order' : 'Booking kendaraan Anda' }}</p>
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
                <a href="{{ route('bookings.create') }}" class="inline-block mt-4 text-sm text-blue-400 hover:text-blue-300">Buat booking pertama →</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                            <th class="px-6 py-3">Kode</th>
                            @if(auth()->user()->isStaff())
                            <th class="px-6 py-3">Customer</th>
                            @endif
                            <th class="px-6 py-3">Kendaraan</th>
                            <th class="px-6 py-3">Servis</th>
                            <th class="px-6 py-3">Tanggal</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Total</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @foreach($bookings as $booking)
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs font-bold text-blue-400">{{ $booking->booking_code }}</span>
                            </td>
                            @if(auth()->user()->isStaff())
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-white">{{ $booking->customer->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $booking->customer->phone }}</p>
                                </div>
                            </td>
                            @endif
                            <td class="px-6 py-4">
                                <p class="text-white">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ $booking->plate_number }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-300">{{ $booking->service->name }}</td>
                            <td class="px-6 py-4 text-slate-400 text-xs">{{ $booking->booking_date->format('d M Y') }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                                        'confirmed' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                        'in_progress' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                        'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'invoiced' => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                                    ];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border {{ $statusColors[$booking->status] ?? '' }}">
                                    @if($booking->status === 'in_progress')
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 pulse-dot"></span>
                                    @endif
                                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-white font-medium">
                                Rp {{ number_format($booking->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('bookings.show', $booking) }}" class="p-2 rounded-lg text-slate-400 hover:text-blue-400 hover:bg-blue-500/10 transition-all" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if(in_array($booking->status, ['completed', 'invoiced']))
                                    <a href="{{ route('bookings.invoice', $booking) }}" class="p-2 rounded-lg text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 transition-all" title="Invoice">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
