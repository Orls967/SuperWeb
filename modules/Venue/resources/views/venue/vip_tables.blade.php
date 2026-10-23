@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🍾</span> VIP Cabana & Daybed Table Bookings
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Minimum spend enforcement, table reservation escrow deposits, and no-show protection.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Venue Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Reserve VIP Table</h3>
                    <form action="{{ route('venue.tables.reserve') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Event</label>
                            <select name="event_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($events as $ev)
                                    <option value="{{ $ev->id }}">{{ $ev->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Table / Daybed Number</label>
                            <input type="text" name="table_number" value="VIP-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Minimum Spend Guarantee (IDR)</label>
                            <input type="number" name="minimum_spend_idr" value="15000000" min="1000000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Reservation Deposit Hold (IDR)</label>
                            <input type="number" name="deposit_amount_idr" value="5000000" min="500000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Hold VIP Table & Lock Escrow
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Reserved VIP Tables</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Ref Code</th>
                                    <th class="p-3">Table</th>
                                    <th class="p-3">Event</th>
                                    <th class="p-3 text-right">Min Spend</th>
                                    <th class="p-3 text-right">Deposit</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($bookings as $b)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $b->booking_code }}</td>
                                        <td class="p-3 font-bold text-white">{{ $b->table_number }}</td>
                                        <td class="p-3 text-gray-300">{{ $b->event ? $b->event->title : 'Event #'.$b->event_id }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($b->minimum_spend_idr) }}</td>
                                        <td class="p-3 text-right font-mono text-yellow-400">Rp {{ number_format($b->deposit_amount_idr) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-900 text-cyan-300 border border-cyan-700">
                                                {{ $b->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No VIP tables reserved yet.</td>
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
