@extends('layouts.app')

@section('title', 'Telematics & Fleet IoT Connected Car')
@section('subtitle', 'Ingest Pipeline Telemetri Skala Tinggi, Agregasi 5-Menitan & Prediktif Maintenance AutoServe (Fase 68)')

@section('content')
    <x-enterprise.module-dashboard module="Telematics" />

<div class="space-y-8" x-data="{ activeTab: 'devices' }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div id="telematics-alert-success" class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">MAPE Prediksi Kerusakan</p>
                <p class="text-xl font-bold text-emerald-400">{{ $kpis['mape'] }}%</p>
                <p class="text-xs text-slate-400 mt-0.5">Mean Time to Service: {{ $kpis['mean_time_to_service'] }}</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Antrean Draf Booking</p>
                <p class="text-xl font-bold text-slate-100">{{ number_format($kpis['draft_bookings_count']) }}</p>
                <p class="text-xs text-blue-400 mt-0.5">Otomatisasi AutoServe</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Pendapatan Preventif</p>
                <p class="text-xl font-bold text-slate-100">Rp {{ number_format($kpis['preventive_revenue']) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">Estimasi Perawatan Unit</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl {{ $kpis['grounded_count'] > 0 ? 'bg-rose-500/20 text-rose-400' : 'bg-slate-700/30 text-slate-400' }} flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Unit Grounded (Tolak Dispatch)</p>
                <p class="text-xl font-bold {{ $kpis['grounded_count'] > 0 ? 'text-rose-400' : 'text-slate-100' }}">{{ $kpis['grounded_count'] }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $kpis['active_devices_count'] }} Unit Normal Operasional</p>
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="flex border-b border-slate-700/70 gap-6">
        <button type="button" @click="activeTab = 'devices'" :class="activeTab === 'devices' ? 'border-indigo-500 text-indigo-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
            Perangkat Terhubung ({{ $devices->count() }})
        </button>
        <button type="button" @click="activeTab = 'aggregates'" :class="activeTab === 'aggregates' ? 'border-cyan-500 text-cyan-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z"/></svg>
            Agregasi 5-Menitan ({{ $aggregates->count() }})
        </button>
        <button type="button" @click="activeTab = 'baselines'" :class="activeTab === 'baselines' ? 'border-amber-500 text-amber-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Rolling Baseline 7 Hari ({{ $baselines->count() }})
        </button>
        <button type="button" @click="activeTab = 'ticks'" :class="activeTab === 'ticks' ? 'border-emerald-500 text-emerald-400 font-semibold' : 'border-transparent text-slate-400 hover:text-slate-300'" class="pb-3 border-b-2 text-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Live Ingest Stream Ticks ({{ $ticks->count() }})
        </button>
    </div>

    {{-- Tab 1: Perangkat IoT Terhubung & Ingest Test --}}
    <div x-show="activeTab === 'devices'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($devices as $device)
                <div class="p-5 rounded-2xl bg-slate-800/40 border {{ $device->status === 'grounded' ? 'border-rose-500/50 bg-rose-500/5' : 'border-slate-700/50' }} backdrop-blur-xl flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono font-semibold text-slate-100 text-sm">{{ $device->device_id }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $device->status === 'grounded' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/40' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' }}">
                                {{ strtoupper($device->status) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">Kendaraan: <span class="text-slate-200 font-medium">{{ $device->vehicle ? $device->vehicle->plate_number : 'Unassigned' }}</span></p>
                        <p class="text-xs text-slate-400">SN: <span class="font-mono text-slate-300">{{ $device->serial_number }}</span> • {{ $device->protocol }}</p>
                        <p class="text-xs text-slate-400 mt-1">Heartbeat Terakhir: <span class="text-slate-300 font-mono">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->diffForHumans() : '-' }}</span></p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-700/50">
                        <form action="{{ route('telematics.devices.ingest', $device) }}" method="POST" class="space-y-2">
                            @csrf
                            <input type="hidden" name="oil_temp_c" value="92.5">
                            <input type="hidden" name="battery_voltage" value="12.6">
                            <input type="hidden" name="fuel_level_pct" value="75.0">
                            <input type="hidden" name="speed_kmh" value="65.0">
                            <input type="hidden" name="rpm" value="2400">
                            <button type="submit" class="w-full py-1.5 px-3 rounded-lg bg-emerald-600/80 hover:bg-emerald-600 text-white font-medium text-xs transition">
                                Kirim Normal Heartbeat Tick
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-3 p-8 text-center text-slate-400 bg-slate-800/40 border border-slate-700/50 rounded-2xl">
                    Belum ada perangkat telematika terdaftar.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Tab 2: Agregasi 5-Menitan (oto_telematics_aggregates) --}}
    <div x-show="activeTab === 'aggregates'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                <span>Agregasi Telemetri Interval 5-Menitan</span>
                <span class="text-xs text-slate-400 font-normal">{{ $aggregates->count() }} window terkomputasi</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/70 text-slate-400">
                            <th class="pb-2">Perangkat</th>
                            <th class="pb-2">Window Start</th>
                            <th class="pb-2">Window End</th>
                            <th class="pb-2">Avg Speed</th>
                            <th class="pb-2">Max RPM</th>
                            <th class="pb-2">Avg Oil Temp</th>
                            <th class="pb-2">Min Voltage</th>
                            <th class="pb-2">Ticks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-slate-300">
                        @forelse($aggregates as $agg)
                            <tr class="hover:bg-slate-700/20">
                                <td class="py-2.5 font-mono text-cyan-400 font-semibold">{{ $agg->device_id }}</td>
                                <td class="py-2.5 font-mono text-slate-300">{{ $agg->window_start }}</td>
                                <td class="py-2.5 font-mono text-slate-400">{{ $agg->window_end }}</td>
                                <td class="py-2.5 font-mono">{{ $agg->avg_speed_kmh }} km/h</td>
                                <td class="py-2.5 font-mono">{{ $agg->max_rpm }}</td>
                                <td class="py-2.5 font-mono text-amber-400 font-semibold">{{ $agg->avg_oil_temp_c }}°C</td>
                                <td class="py-2.5 font-mono text-slate-300">{{ $agg->min_battery_voltage }}V</td>
                                <td class="py-2.5"><span class="px-2 py-0.5 rounded bg-slate-700 text-slate-200 font-mono text-[11px]">{{ $agg->tick_count }} ticks</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-400">Belum ada agregasi 5-menitan yang terkomputasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tab 3: Rolling Baseline 7 Hari --}}
    <div x-show="activeTab === 'baselines'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                <span>Rolling Baseline 7 Hari per Kendaraan</span>
                <span class="text-xs text-slate-400 font-normal">{{ $baselines->count() }} baseline aktif</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/70 text-slate-400">
                            <th class="pb-2">Kendaraan</th>
                            <th class="pb-2">Avg Oil Temp (7-Hari)</th>
                            <th class="pb-2">Avg Battery Voltage</th>
                            <th class="pb-2">Konsumsi BBM Rate</th>
                            <th class="pb-2">Jumlah Sampel</th>
                            <th class="pb-2">Terakhir Dihitung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-slate-300">
                        @forelse($baselines as $base)
                            <tr class="hover:bg-slate-700/20">
                                <td class="py-2.5 font-medium text-slate-100">Kendaraan #{{ $base->vehicle_id }}</td>
                                <td class="py-2.5 font-mono text-amber-400 font-semibold">{{ $base->avg_oil_temp_c }}°C</td>
                                <td class="py-2.5 font-mono text-cyan-400">{{ $base->avg_battery_voltage }}V</td>
                                <td class="py-2.5 font-mono">{{ $base->avg_fuel_consumption_rate }} L/100km</td>
                                <td class="py-2.5 font-mono text-slate-300">{{ number_format($base->sample_count) }} data points</td>
                                <td class="py-2.5 font-mono text-slate-400">{{ $base->calculated_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400">Belum ada baseline kendaraan terhitung.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Tab 4: Live Telematics Stream Ticks --}}
    <div x-show="activeTab === 'ticks'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-semibold text-slate-100 mb-4 flex items-center justify-between">
                <span>Stream Ticks Telemetri Terkini</span>
                <span class="text-xs text-slate-400 font-normal">{{ $ticks->count() }} tick terbaru</span>
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/70 text-slate-400">
                            <th class="pb-2">Seq</th>
                            <th class="pb-2">Device ID</th>
                            <th class="pb-2">Recorded At</th>
                            <th class="pb-2">Speed</th>
                            <th class="pb-2">RPM</th>
                            <th class="pb-2">Suhu Oli</th>
                            <th class="pb-2">Tegangan Baterai</th>
                            <th class="pb-2">BBM</th>
                            <th class="pb-2">DTC Code</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-slate-300">
                        @forelse($ticks as $tick)
                            <tr class="hover:bg-slate-700/20">
                                <td class="py-2.5 font-mono text-slate-400">#{{ $tick->sequence }}</td>
                                <td class="py-2.5 font-mono text-indigo-400">{{ $tick->device_id }}</td>
                                <td class="py-2.5 font-mono text-slate-300">{{ $tick->recorded_at }}</td>
                                <td class="py-2.5 font-mono">{{ $tick->speed_kmh }} km/h</td>
                                <td class="py-2.5 font-mono">{{ $tick->rpm }}</td>
                                <td class="py-2.5 font-mono font-semibold {{ $tick->oil_temp_c > 105 ? 'text-rose-400' : 'text-amber-400' }}">{{ $tick->oil_temp_c }}°C</td>
                                <td class="py-2.5 font-mono {{ $tick->battery_voltage < 11.8 ? 'text-rose-400 font-semibold' : 'text-cyan-400' }}">{{ $tick->battery_voltage }}V</td>
                                <td class="py-2.5 font-mono text-slate-300">{{ $tick->fuel_level_pct }}%</td>
                                <td class="py-2.5">
                                    @if($tick->dtc_code)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono bg-rose-500/20 text-rose-400 border border-rose-500/40">
                                            {{ $tick->dtc_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-500 font-mono">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-6 text-center text-slate-400">Belum ada tick telemetri yang masuk.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
