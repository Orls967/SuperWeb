@props([])

@php
    $telemetryData = [
        [
            'id' => 'control_tower',
            'title' => 'Supply Chain AI Tower',
            'sbu' => 'Logistics & WMS',
            'status' => 'NORMAL',
            'statusColor' => 'emerald',
            'badge' => '98.50% OTIF',
            'primary' => '28.400 unit/jam',
            'sub' => '142 Rute Aktif • 18 Reallokasi Otonom',
            'icon' => '<svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        ],
        [
            'id' => 'smart_grid_egy',
            'title' => 'Microgrid & Smart Grid',
            'sbu' => 'Energi & PPA',
            'status' => 'STABLE',
            'statusColor' => 'amber',
            'badge' => '450 / 600 MW',
            'primary' => 'SoC Baterai 87.50%',
            'sub' => 'Penetrasi EBT 4.200 bps • 50.012 Hz',
            'icon' => '<svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
        ],
        [
            'id' => 'hospital_igd',
            'title' => 'Hospital Clinical Safety',
            'sbu' => 'Kesehatan & IGD',
            'status' => 'OPTIMAL',
            'statusColor' => 'rose',
            'badge' => 'BOR 76.40%',
            'primary' => 'Suhu Vaksin 4.15°C',
            'sub' => 'Antrean IGD 6 Pasien • 8 ICU Tersedia',
            'icon' => '<svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
        ],
        [
            'id' => 'mining_dispatch',
            'title' => 'Mining Fleet Dispatch',
            'sbu' => 'Tambang & Smelter',
            'status' => 'DISPATCHING',
            'statusColor' => 'cyan',
            'badge' => '145.000 Ton',
            'primary' => '48 Haul Fleet Aktif',
            'sub' => 'Strip Ratio 3.20x • Road Safety 99.80%',
            'icon' => '<svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
        ],
        [
            'id' => 'rwa_orderbook',
            'title' => 'RWA Tokenized Escrow',
            'sbu' => 'Fintech & Aset RWA',
            'status' => 'LIQUID',
            'statusColor' => 'purple',
            'badge' => 'Paritas 100.00%',
            'primary' => 'Rp 250.000.000.000',
            'sub' => 'Coverage 125.00% • 3.420 Investor Aktif',
            'icon' => '<svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        ],
        [
            'id' => 'telecom_5g_tlx',
            'title' => '5G Slicing & Roaming',
            'sbu' => 'Telekomunikasi 5G',
            'status' => 'LOW_LATENCY',
            'statusColor' => 'blue',
            'badge' => 'P99 2.4 ms',
            'primary' => '12 Slices Aktif',
            'sub' => 'PUE Data Center 1.18x • Loss 0.02%',
            'icon' => '<svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>',
        ],
    ];
@endphp

<div class="enterprise-live-telemetry-ticker-container p-3 bg-slate-950/80 border-b border-slate-800"
     x-data="{
        isStreaming: true,
        lastPulse: 'Baru saja',
        selectedCenter: null,
        pulseCount: 1,
        telemetries: {{ json_encode($telemetryData) }},
        init() {
            setInterval(() => {
                if (this.isStreaming) {
                    this.pulseCount++;
                    this.lastPulse = this.pulseCount % 2 === 0 ? 'Aktif (1 detik lalu)' : 'Baru saja';
                }
            }, 3000);
        }
     }">
    <div class="flex items-center justify-between mb-2 px-1">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-bold text-slate-200 uppercase tracking-wider font-mono">Live Telemetry 6 Command Center (SSE Stream) (demo)</span>
        </div>
        <div class="flex items-center gap-2 text-[10px] font-mono text-slate-400">
            <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-700 text-indigo-300">Protoc text/event-stream (demo)</span>
            <span class="text-emerald-400" x-text="lastPulse"></span>
        </div>
    </div>

    {{-- Grid 6 Command Centers --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
        @foreach ($telemetryData as $center)
            <div class="p-2 rounded-lg bg-slate-900/90 border border-slate-800 hover:border-indigo-500/50 transition-all flex flex-col justify-between"
                 data-center-id="{{ $center['id'] }}">
                <div>
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <div class="flex items-center gap-1.5 truncate">
                            {!! $center['icon'] !!}
                            <span class="text-[11px] font-bold text-slate-200 truncate">{{ $center['title'] }}</span>
                        </div>
                    </div>
                    <div class="text-xs font-extrabold text-white font-mono">{{ $center['primary'] }}</div>
                    <div class="text-[9px] text-slate-400 truncate mt-0.5">{{ $center['sub'] }}</div>
                </div>
                <div class="mt-2 flex items-center justify-between pt-1 border-t border-slate-800/60">
                    <span class="text-[9px] px-1.5 py-0.5 rounded font-mono font-bold bg-slate-800 text-slate-300">
                        {{ $center['badge'] }}
                    </span>
                    <span class="text-[8px] font-mono text-emerald-400 font-semibold">{{ $center['status'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
