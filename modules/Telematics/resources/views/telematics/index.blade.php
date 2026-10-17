@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Telematics & Fleet IoT Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Streaming Telemetri Kendaraan, Deteksi Anomali OBD-II DTC & Protokol Auto-Grounding</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Devices -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Perangkat IoT Terhubung</h3>
                    <div class="space-y-3">
                        @forelse($devices as $device)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $device->device_id }}</div>
                                    <div class="text-xs text-gray-400">Serial: {{ $device->serial_number }} • Protokol: {{ $device->protocol }}</div>
                                    <div class="text-xs text-indigo-400 mt-1">Status: {{ $device->status }} • Heartbeat: {{ $device->last_heartbeat_at?->diffForHumans() }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('telematics.devices.ingest', $device) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="oil_temp_c" value="95.0">
                                        <input type="hidden" name="battery_voltage" value="12.4">
                                        <input type="hidden" name="fuel_level_pct" value="70.0">
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Kirim Heartbeat Tick
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada perangkat telematika terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Baselines -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Rolling Baseline 7 Hari</h3>
                    <div class="space-y-2">
                        @forelse($baselines as $base)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">Kendaraan #{{ $base->vehicle_id }}</div>
                                    <div class="text-xs text-gray-400">Sampel: {{ $base->sample_count }} data point</div>
                                </div>
                                <div class="text-right text-xs text-gray-300">
                                    <div>Oil Avg: <span class="text-amber-400 font-semibold">{{ $base->avg_oil_temp_c }}°C</span></div>
                                    <div>Volt Avg: <span class="text-cyan-400 font-semibold">{{ $base->avg_battery_voltage }}V</span></div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada baseline terhitung.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Telematics Ticks -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Stream Telemetri Terkini</h3>
                <div class="space-y-2">
                    @forelse($ticks as $tick)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                            <div>
                                <div class="text-white font-medium">{{ $tick->device_id }} — Seq #{{ $tick->sequence }}</div>
                                <div class="text-xs text-gray-400">Kecepatan: {{ $tick->speed_kmh }} km/h • RPM: {{ $tick->rpm }} • Bahan Bakar: {{ $tick->fuel_level_pct }}%</div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-300">Oil: {{ $tick->oil_temp_c }}°C • Volt: {{ $tick->battery_voltage }}V</span>
                                @if($tick->dtc_code)
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-red-900 text-red-200 ml-2">
                                        DTC: {{ $tick->dtc_code }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada stream telemetri masuk.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
