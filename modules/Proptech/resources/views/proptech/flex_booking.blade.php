@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🏢</span> Flex-Space & Co-Working Smart Booking
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 78: Real-time anti-overlap lock, deposit holding, and automated cryptographic smart lock access.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('proptech.flex.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Flex Overview
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Book Desk or Meeting Suite</h3>
                    <form action="{{ route('proptech.intelligence.flex-booking.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Flex-Space Inventory</label>
                            <select name="flex_space_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($spaces as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }} (Rp {{ number_format($sp->rate_per_hour_idr) }}/hr)</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Start Time</label>
                            <input type="datetime-local" name="start_time" value="{{ date('Y-m-d\T10:00') }}" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">End Time</label>
                            <input type="datetime-local" name="end_time" value="{{ date('Y-m-d\T14:00') }}" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Security Deposit Hold (IDR)</label>
                            <input type="number" name="deposit_amount_idr" value="100000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Confirm Time-Locked Booking
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Active & Confirmed Bookings</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Booking Code</th>
                                    <th class="p-3">Space</th>
                                    <th class="p-3">Schedule</th>
                                    <th class="p-3 text-right">Total (IDR)</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($bookings as $b)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $b->booking_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $b->space ? $b->space->name : 'Space #'.$b->flex_space_id }}</td>
                                        <td class="p-3">{{ $b->start_time ? $b->start_time->format('d M H:i') : '' }} &ndash; {{ $b->end_time ? $b->end_time->format('H:i') : '' }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($b->total_price_idr) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-900 text-cyan-300 border border-cyan-700">
                                                {{ $b->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No flex bookings found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
