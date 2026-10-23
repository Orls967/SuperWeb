@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🛡️</span> Gate Turnstile Access & Anti-Passback Control
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Legal age check (21+), zone occupancy cap enforcement, and single-scan ticket burn.
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

            @if(session('error'))
                <div class="mb-4 p-4 bg-rose-900/60 border border-rose-500 rounded-lg text-rose-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Turnstile Barcode Scanner</h3>
                    <form action="{{ route('venue.ticketing.gate.scan') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Valid Ticket</label>
                            <select name="ticket_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @forelse($validTickets as $vt)
                                    <option value="{{ $vt->id }}">{{ $vt->ticket_number }} (Buyer #{{ $vt->buyer_user_id }})</option>
                                @empty
                                    <option disabled>No valid tickets available</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Zone Entry</label>
                            <select name="zone_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($zones as $z)
                                    <option value="{{ $z->id }}">{{ $z->name }} (Occupancy: {{ $z->current_occupancy }}/{{ $z->capacity_limit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Verified Attendee Age</label>
                            <input type="number" name="attendee_age" value="23" min="16" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Gate Turnstile Code</label>
                            <input type="text" name="gate_code" value="TURNSTILE-VIP-A" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                            Scan Ticket & Release Turnstile
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Live Zone Capacity Dashboard</h3>
                    <div class="space-y-3">
                        @forelse($zones as $z)
                            <div class="p-4 bg-gray-800/80 rounded border border-gray-700">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-bold text-white text-sm">{{ $z->name }}</span>
                                    <span class="font-mono text-xs text-gray-400">{{ $z->current_occupancy }} / {{ $z->capacity_limit }}</span>
                                </div>
                                @php
                                    $pct = $z->capacity_limit > 0 ? min(100, round(($z->current_occupancy / $z->capacity_limit) * 100)) : 0;
                                    $barColor = $pct >= 90 ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
                                @endphp
                                <div class="w-full bg-gray-700 h-2 rounded-full overflow-hidden">
                                    <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No entertainment zones registered.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
