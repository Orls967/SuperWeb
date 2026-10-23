@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🏨</span> Hotel Room Reservations & Inventory Lock
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 91: Pessimistic inventory locking, anti-overlap dates, and automatic folio ledger provisioning.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('hotel.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Hotel Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Book Room Reservation</h3>
                    <form action="{{ route('hotel.pms.reservations.book') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Property</label>
                            <select name="property_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->name }} ({{ $prop->city }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Available Room</label>
                            <select name="room_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($rooms as $rm)
                                    <option value="{{ $rm->id }}">Room {{ $rm->room_number }} ({{ $rm->room_type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Check-In Date</label>
                            <input type="date" name="check_in_date" value="{{ date('Y-m-d') }}" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Check-Out Date</label>
                            <input type="date" name="check_out_date" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Daily Rate (IDR)</label>
                            <input type="number" name="daily_rate_idr" value="1250000" min="100000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Confirm Room Reservation
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Confirmed Reservations</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Res #</th>
                                    <th class="p-3">Dates</th>
                                    <th class="p-3 text-right">Daily Rate</th>
                                    <th class="p-3 text-right">Total Charge</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($reservations as $res)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $res->reservation_number }}</td>
                                        <td class="p-3">{{ $res->check_in_date ? $res->check_in_date->format('d M') : '' }} &ndash; {{ $res->check_out_date ? $res->check_out_date->format('d M') : '' }}</td>
                                        <td class="p-3 text-right font-mono text-white">Rp {{ number_format($res->daily_rate_idr) }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($res->total_room_charge_idr) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">
                                                {{ $res->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No reservations recorded yet.</td>
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
