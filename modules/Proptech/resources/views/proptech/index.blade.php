@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Proptech & Smart Building Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Monitoring IoT Sensor Gedung, Otomasi HVAC Deterministik & Billing Utilitas Berbasis Ledger</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Zones & Billing Action -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Zona Gedung & Utilitas</h3>
                    <div class="space-y-3">
                        @forelse($zones as $zone)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $zone->zone_code }} — {{ $zone->name }}</div>
                                    <div class="text-xs text-gray-400">Lantai {{ $zone->floor_level }} • Luas: {{ $zone->area_sqm }} sqm • Tarif: Rp {{ number_format((float) $zone->current_kwh_rate, 0, ',', '.') }}/kWh</div>
                                    <div class="text-xs text-indigo-400 mt-1">Suhu: {{ $zone->current_temp_c }}°C • Okupansi: {{ $zone->current_occupancy }} orang</div>
                                </div>
                                <div>
                                    <form action="{{ route('proptech.zones.bill', $zone) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                            Generate Bill
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data zona gedung.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Sensor Readings -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Sensor IoT Terkini</h3>
                    <div class="space-y-2">
                        @forelse($sensors as $sensor)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $sensor->sensor_code }} ({{ $sensor->sensor_type }})</div>
                                    <div class="text-xs text-gray-400">Zona: {{ $sensor->zone?->name }} • Waktu: {{ $sensor->recorded_at?->diffForHumans() }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-semibold text-emerald-400">{{ $sensor->reading_value }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada pembacaan sensor.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Utility Billings -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Tagihan Utilitas Terbit</h3>
                <div class="space-y-2">
                    @forelse($billings as $billing)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                            <div>
                                <div class="text-white font-medium">{{ $billing->billing_code }}</div>
                                <div class="text-xs text-gray-400">Zona: {{ $billing->zone?->name }} • Pemakaian: {{ number_format((float) $billing->total_kwh, 2) }} kWh • Emisi: {{ $billing->carbon_emission_kg }} kg CO2e</div>
                            </div>
                            <div class="text-right">
                                <span class="text-white font-bold text-sm">Rp {{ number_format($billing->total_amount_idr, 0, ',', '.') }}</span>
                                <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-emerald-900 text-emerald-200 ml-2">
                                    {{ $billing->status }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada tagihan utilitas terbit.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
