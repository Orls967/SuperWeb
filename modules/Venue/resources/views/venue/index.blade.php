@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Venue" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Beach Club, Venue & Entertainment Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Kontrol Pintu Masuk Anti-Replay, Manajemen Zona Kapasitas & Escrow Tiket Digital</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Venues & Events -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Event & Venue Aktif</h3>
                    <div class="space-y-3">
                        @forelse($events as $event)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $event->title }}</div>
                                <div class="text-xs text-gray-400">Kode: {{ $event->event_code }} • Venue: {{ $event->venue?->name }}</div>
                                <div class="text-xs text-indigo-400 mt-1">Doors Open: {{ $event->doors_open_at?->toFormattedDateString() }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada event terjadwal.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Zones -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Zona & Crowd Density</h3>
                    <div class="space-y-3">
                        @forelse($zones as $zone)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $zone->name }}</div>
                                    <div class="text-xs text-gray-400">Okupansi: {{ $zone->current_occupancy }} / {{ $zone->capacity_limit }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $zone->current_occupancy < $zone->capacity_limit ? 'bg-emerald-900 text-emerald-200' : 'bg-red-900 text-red-200' }}">
                                        {{ $zone->current_occupancy < $zone->capacity_limit ? 'OPEN' : 'FULL' }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada zona terkonfigurasi.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Tickets & Gate Scan -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Tiket & Gate Entry</h3>
                    <div class="space-y-3">
                        @forelse($tickets as $tck)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $tck->ticket_number }}</div>
                                <div class="text-xs text-gray-400 mb-2">Event: {{ $tck->event?->title }} • Harga: Rp {{ number_format($tck->price_paid_idr, 0, ',', '.') }}</div>
                                
                                @if($tck->status === 'VALID' && $zones->isNotEmpty())
                                    <form action="{{ route('venue.gate.scan', $tck) }}" method="POST" class="space-y-2 pt-2 border-t border-gray-700">
                                        @csrf
                                        <input type="hidden" name="zone_id" value="{{ $zones->first()->id }}">
                                        <input type="hidden" name="patron_age" value="22">
                                        <button type="submit" class="w-full py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Pindai Gate Masuk (Scan QR)
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-emerald-400 font-semibold">Status: {{ $tck->status }}</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Tidak ada tiket aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
