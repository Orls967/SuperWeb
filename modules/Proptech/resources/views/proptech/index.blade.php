@extends('layouts.app')

@section('content')
<div class="py-8 bg-slate-950 min-h-screen text-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl border border-indigo-900/40 shadow-2xl flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        ● IoT Live Mesh
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        Fase 76 Smart Building & ESG
                    </span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white mt-2">Proptech Smart Building Operations</h1>
                <p class="text-xs text-slate-400 mt-1">Telemetri IoT Sensor Gedung, Otomasi Histeresis HVAC, Overtime AC Billing, dan Preskriptif ESG Real-Time.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('proptech.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition">
                    ↻ Refresh Telemetri
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <span class="text-xs text-emerald-400 font-mono">OK</span>
            </div>
        @endif

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Zona Terkelola</div>
                <div class="mt-2 text-2xl font-bold text-white">{{ $zones->count() }} <span class="text-xs font-normal text-slate-500">zona aktif</span></div>
                <div class="mt-1 text-xs text-indigo-400">Baseline Otomatis per m²</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">IoT Sensor Telemetri</div>
                <div class="mt-2 text-2xl font-bold text-emerald-400">{{ $sensors->where('is_online', true)->count() }} <span class="text-xs font-normal text-slate-500">/ {{ $sensors->count() }} online</span></div>
                <div class="mt-1 text-xs text-slate-400">Heartbeat check interval < 10m</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Emisi Karbon Kumulatif</div>
                <div class="mt-2 text-2xl font-bold text-amber-400">{{ number_format($totalCarbonEmissionKg, 2) }} <span class="text-xs font-normal text-slate-500">kg CO2e</span></div>
                <div class="mt-1 text-xs text-slate-400">Faktor emisi grid: 0.85 kg/kWh</div>
            </div>

            <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-800 shadow-lg">
                <div class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Tagihan & Overtime</div>
                <div class="mt-2 text-2xl font-bold text-white">Rp {{ number_format($totalUtilityBilledIdr, 0, ',', '.') }}</div>
                <div class="mt-1 text-xs text-emerald-400">Overtime AC: Rp {{ number_format($totalOvertimeBilledIdr, 0, ',', '.') }}</div>
            </div>
        </div>

        <!-- Prescriptive Ops & AI Recommendations -->
        @if($prescriptiveOps->isNotEmpty())
        <div class="bg-slate-900/90 rounded-2xl border border-indigo-900/50 p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
                    <h2 class="text-lg font-semibold text-white">Prescriptive Operations & Optimasi Energi</h2>
                </div>
                <span class="text-xs text-indigo-300 font-mono">{{ $prescriptiveOps->count() }} Rekomendasi Terdaftar</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($prescriptiveOps as $op)
                    <div class="bg-slate-950/70 p-4 rounded-xl border border-slate-800 hover:border-indigo-600/50 transition">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider font-mono">{{ $op->action_code }}</span>
                            <span class="px-2 py-0.5 text-xs rounded font-semibold bg-indigo-900/50 text-indigo-200 border border-indigo-700/50">{{ $op->status }}</span>
                        </div>
                        <div class="mt-2 text-sm text-slate-200 font-medium">{{ $op->zone?->name ?? 'Zona Gedung' }}</div>
                        <div class="mt-1 text-xs text-slate-400 leading-relaxed">{{ $op->recommendation_text }}</div>
                        <div class="mt-3 pt-3 border-t border-slate-800/80 flex justify-between items-center text-xs">
                            <span class="text-slate-400">Potensi Hemat: <strong class="text-white">{{ $op->potential_kwh_saved }} kWh</strong></span>
                            <span class="text-emerald-400 font-semibold">+Rp {{ number_format($op->potential_saving_idr, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Main Workspace: Zones & Sensor Feeds -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left: Building Zones & Billing Generation Form -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-white">Zona Gedung & Monitoring HVAC</h2>
                        <span class="text-xs text-slate-400 font-mono">Histeresis Target 24°C</span>
                    </div>

                    <div class="space-y-4">
                        @forelse($zones as $zone)
                            <div class="p-5 bg-slate-950/80 rounded-xl border border-slate-800 hover:border-slate-700 transition">
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300">{{ $zone->zone_code }}</span>
                                            <span class="text-white font-semibold">{{ $zone->name }}</span>
                                            <span class="text-xs text-slate-400">(Lt. {{ $zone->floor_level }})</span>
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            Luas: <span class="text-slate-200">{{ $zone->area_sqm }} m²</span> • 
                                            Baseline: <span class="text-slate-200">{{ $zone->baseline_kwh_per_sqm ?? 12.5 }} kWh/m²</span> • 
                                            Tarif Dasar: <span class="text-slate-200">Rp {{ number_format((float) $zone->current_kwh_rate, 0, ',', '.') }}/kWh</span>
                                        </div>
                                        <div class="flex items-center gap-4 text-xs pt-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-slate-500">Suhu:</span>
                                                <span class="font-bold {{ $zone->current_temp_c > 25.5 ? 'text-amber-400' : ($zone->current_temp_c < 22.0 ? 'text-cyan-400' : 'text-emerald-400') }}">
                                                    {{ $zone->current_temp_c }}°C
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-slate-500">Okupansi:</span>
                                                <span class="font-bold text-indigo-400">{{ $zone->current_occupancy }} Org</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick Bill Action -->
                                    <div class="bg-slate-900 p-3 rounded-lg border border-slate-800 flex flex-col gap-2 min-w-[220px]">
                                        <form action="{{ route('proptech.zones.bill', $zone) }}" method="POST" class="space-y-2">
                                            @csrf
                                            <input type="hidden" name="tenant_id" value="1">
                                            <div class="flex items-center justify-between text-xs">
                                                <label class="text-slate-400">Overtime AC (jam):</label>
                                                <input type="number" step="0.5" min="0" max="24" name="overtime_ac_hours" value="0" class="w-16 bg-slate-950 border border-slate-700 rounded px-2 py-0.5 text-xs text-right text-white focus:outline-none focus:border-indigo-500">
                                            </div>
                                            <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded transition shadow flex items-center justify-center gap-1">
                                                <span>⚡ Terbitkan Invoice</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 text-sm">Belum ada zona gedung terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Utility Billings Table -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white mb-4">Riwayat Tagihan Utilitas & Audit Emisi</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950 text-slate-400 uppercase font-mono text-[10px]">
                                <tr>
                                    <th class="p-3">Kode Billing</th>
                                    <th class="p-3">Zona</th>
                                    <th class="p-3 text-right">Pemakaian</th>
                                    <th class="p-3 text-right">Overtime AC</th>
                                    <th class="p-3 text-right">Total Biaya</th>
                                    <th class="p-3 text-right">Emisi GRK</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @forelse($billings as $billing)
                                    <tr class="hover:bg-slate-800/40 transition font-mono">
                                        <td class="p-3 font-semibold text-white">{{ $billing->billing_code }}</td>
                                        <td class="p-3 text-slate-300">{{ $billing->zone?->name ?? 'N/A' }}</td>
                                        <td class="p-3 text-right">{{ number_format((float) $billing->total_kwh, 2) }} kWh</td>
                                        <td class="p-3 text-right text-amber-400">Rp {{ number_format((int) $billing->overtime_ac_amount_idr, 0, ',', '.') }}</td>
                                        <td class="p-3 text-right font-bold text-emerald-400">Rp {{ number_format($billing->total_amount_idr, 0, ',', '.') }}</td>
                                        <td class="p-3 text-right text-slate-300">{{ number_format((float) $billing->carbon_emission_kg, 2) }} kg</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-300 border border-emerald-800">
                                                {{ $billing->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-slate-500">Belum ada tagihan utilitas terbit.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Sensor Stream & Telemetry Feeds -->
            <div class="space-y-4">
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-white">Live IoT Feeds</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-900/40 text-emerald-400 border border-emerald-800">
                            Mesh Online
                        </span>
                    </div>

                    <div class="space-y-2.5 max-h-[500px] overflow-y-auto pr-1">
                        @forelse($sensors as $sensor)
                            <div class="p-3 bg-slate-950/80 rounded-lg border border-slate-800 flex justify-between items-center text-xs">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $sensor->is_online ? 'bg-emerald-400' : 'bg-rose-500' }}"></span>
                                        <span class="text-white font-mono font-medium">{{ $sensor->sensor_code }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $sensor->sensor_type }} • {{ $sensor->zone?->zone_code }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-white font-mono text-sm">
                                        {{ $sensor->reading_value }}
                                        <span class="text-[10px] font-normal text-slate-400">
                                            @if($sensor->sensor_type === 'TEMP') °C
                                            @elseif($sensor->sensor_type === 'POWER_KWH') kWh
                                            @elseif($sensor->sensor_type === 'OCCUPANCY') org
                                            @endif
                                        </span>
                                    </span>
                                    <div class="text-[10px] text-slate-500">
                                        {{ $sensor->recorded_at?->diffForHumans() ?? 'baru saja' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-slate-500 text-xs text-center p-4">Tidak ada sensor terdeteksi.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Recent HVAC Commands -->
                <div class="bg-slate-900/90 rounded-2xl border border-slate-800 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white mb-3">Log Eksekusi Otomasi HVAC</h2>
                    <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1 text-xs font-mono">
                        @forelse($hvacCommands as $cmd)
                            <div class="p-2.5 bg-slate-950/60 rounded border border-slate-800 flex justify-between items-center">
                                <div>
                                    <div class="text-indigo-300 font-semibold">{{ $cmd->command_type }}</div>
                                    <div class="text-[10px] text-slate-400">Target: {{ $cmd->target_temp_c }}°C • Fan: {{ $cmd->fan_speed }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300">{{ $cmd->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-slate-500 text-xs text-center p-4">Belum ada perintah HVAC otomatis.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection

