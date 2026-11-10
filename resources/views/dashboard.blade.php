@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', auth()->user()->isStaff() ? 'Overview Bengkel' : 'Selamat Datang, ' . auth()->user()->name)

@section('content')
<div class="space-y-6">

    {{-- STATS CARDS MENGGUNAKAN X-DS.STAT --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @if(auth()->user()->isStaff())
            <x-ds.stat label="Total Booking" :value="$stats['total_bookings']" hint="Semua status" icon="cube" />
            <x-ds.stat label="Pending" :value="$stats['pending_bookings']" hint="Perlu konfirmasi" icon="clock" change-type="neutral" />
            <x-ds.stat label="In Progress" :value="$stats['in_progress']" hint="Sedang dikerjakan" icon="refresh" change-type="positive" />
            <x-ds.stat label="Total Revenue" :value="'Rp ' . number_format($stats['total_revenue'], 0, ',', '.')" hint="Pendapatan bengkel" icon="chart-bar" change-type="positive" />
        @else
            <x-ds.stat label="Total Booking" :value="$stats['total_bookings']" hint="Pesanan servis Anda" icon="cube" />
            <x-ds.stat label="Menunggu" :value="$stats['pending_bookings']" hint="Menunggu antrean" icon="clock" change-type="neutral" />
            <x-ds.stat label="Dikerjakan" :value="$stats['in_progress']" hint="Dalam perbaikan" icon="refresh" change-type="positive" />
            <x-ds.stat label="Selesai" :value="$stats['completed']" hint="Servis rampung" icon="check-circle" change-type="positive" />
        @endif
    </div>

    {{-- LOW STOCK ALERT MENGGUNAKAN X-DS.ERROR --}}
    @if(auth()->user()->isStaff() && $stats['low_stock_parts'] > 0)
        <x-ds.error :message="'Peringatan Stok Rendah: ' . $stats['low_stock_parts'] . ' sparepart memiliki stok \u{2264} 5 unit.'" />
    @endif

    {{-- BOOKING LIST MENGGUNAKAN X-DS.CARD & X-DS.TABLE CARD STACK --}}
    <x-ds.card>
        <x-slot:headerActions>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Daftar Booking</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ auth()->user()->isStaff() ? 'Semua job order kendaraan' : 'Booking kendaraan Anda' }}</p>
                </div>
                <a href="{{ route('bookings.create') }}">
                    <x-ds.button variant="primary" size="sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Booking Baru
                    </x-ds.button>
                </a>
            </div>
        </x-slot:headerActions>

        @if($bookings->isEmpty())
            <x-ds.empty
                title="Belum Ada Booking"
                description="Belum ada riwayat pemesanan servis kendaraan."
                action-label="Buat Booking Pertama"
                :action-url="route('bookings.create')"
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <tr>
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
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($bookings as $booking)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $booking->booking_code }}</span>
                            </td>
                            @if(auth()->user()->isStaff())
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $booking->customer->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->customer->phone }}</p>
                                </div>
                            </td>
                            @endif
                            <td class="px-6 py-4">
                                <p class="text-slate-900 dark:text-white">{{ $booking->vehicle_brand }} {{ $booking->vehicle_model }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $booking->plate_number }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $booking->service->name }}</td>
                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs">{{ $booking->booking_date->format('d M Y') }}</td>
                            <td class="px-6 py-4">
                                <x-ds.badge-status :status="$booking->status" />
                            </td>
                            <td class="px-6 py-4 text-slate-900 dark:text-white font-medium">
                                Rp {{ number_format($booking->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('bookings.show', $booking) }}" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" title="Detail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    @if(in_array($booking->status, ['completed', 'invoiced']))
                                    <a href="{{ route('bookings.invoice', $booking) }}" class="p-2 rounded-lg text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all" title="Invoice">
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
    </x-ds.card>
</div>
@endsection
