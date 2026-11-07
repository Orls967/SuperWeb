<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta Okupansi Zona & Pengawasan Kerumunan — Crowd Safety</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tabular-nums { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex flex-col font-sans">
    <!-- Navbar -->
    <nav class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-4">
            <span class="text-emerald-400 font-black text-xl tracking-wider">SUPERWEB // VENUE</span>
            <span class="text-xs bg-rose-950 text-rose-400 border border-rose-800 px-2 py-0.5 rounded font-mono font-semibold">CROWD SAFETY</span>
        </div>
        <div class="flex items-center space-x-6 text-sm">
            <a href="/venue/events" class="text-slate-400 hover:text-white transition-colors">Acara & Tiket</a>
            <a href="/venue/gate" class="text-slate-400 hover:text-white transition-colors">Layar Gerbang 375px</a>
            <a href="/venue/zones" class="text-emerald-400 font-bold border-b-2 border-emerald-400 pb-1">Peta Kerumunan</a>
            <a href="/venue/tables" class="text-slate-400 hover:text-white transition-colors">Meja VIP</a>
            <a href="/venue/permits" class="text-slate-400 hover:text-white transition-colors">Izin Keramaian</a>
            <a href="/venue/reports" class="text-slate-400 hover:text-white transition-colors">Laporan & Audit</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        
        <!-- Alerts -->
        @if(session('success'))
            <div class="bg-emerald-950/80 border border-emerald-500 text-emerald-200 px-4 py-3 rounded-xl flex items-center justify-between shadow-lg">
                <span class="text-sm font-medium">{{ session('success') }}</span>
                <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
            </div>
        @endif

        <!-- Header -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-100 tracking-tight">Peta Okupansi Zona & Kontrol Keselamatan (V3)</h1>
                <p class="text-sm text-slate-400 mt-1">
                    Pemantauan kapasitas hukum, kapasitas efektif keselamatan (ZoneCapacityGovernor V3 buffer 500 bps), status darurat lockdown, dan evakuasi.
                </p>
            </div>
            <div class="flex items-center space-x-3 text-xs font-mono">
                <span class="px-3 py-1 rounded-lg bg-emerald-950 text-emerald-400 border border-emerald-800 font-bold">NORMAL < 60%</span>
                <span class="px-3 py-1 rounded-lg bg-amber-950 text-amber-400 border border-amber-800 font-bold">WASPADA 60-84%</span>
                <span class="px-3 py-1 rounded-lg bg-rose-950 text-rose-400 border border-rose-800 font-bold animate-pulse">KRITIS ≥ 85%</span>
            </div>
        </div>

        <!-- SVG Heatmap Visualizer (6 Zona Venue Interaktif) -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h2 class="text-base font-bold text-slate-200 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    <span>SVG Heatmap Denah Kerumunan (Visual Density Layout)</span>
                </h2>
                <span class="text-xs font-mono text-slate-500">Buffer Safety 500 bps (95% Legal Max)</span>
            </div>

            <!-- SVG Layout -->
            <div class="w-full bg-slate-950 rounded-xl p-4 border border-slate-800 overflow-hidden flex items-center justify-center">
                <svg viewBox="0 0 900 400" class="w-full max-w-4xl h-auto select-none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Venue Boundary Outline -->
                    <rect x="10" y="10" width="880" height="380" rx="16" fill="#090d16" stroke="#1e293b" stroke-width="3"/>
                    
                    <!-- Gate Entry Indicator -->
                    <rect x="20" y="160" width="40" height="80" rx="6" fill="#047857" stroke="#10b981" stroke-width="2"/>
                    <text x="40" y="205" fill="#ecfdf5" font-size="11" font-weight="bold" text-anchor="middle" transform="rotate(-90 40 205)">GERBANG MASUK</text>

                    <!-- Zona 1: Main Stage / Dancefloor -->
                    <g id="zone-svg-1" class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="80" y="30" width="340" height="200" rx="12" fill="#1e1b4b" stroke="#6366f1" stroke-width="2" fill-opacity="0.7"/>
                        <text x="250" y="65" fill="#e0e7ff" font-size="14" font-weight="900" text-anchor="middle">MAIN STAGE & DANCEFLOOR</text>
                        <text x="250" y="95" fill="#a5b4fc" font-size="12" font-family="monospace" text-anchor="middle">Kapasitas Legal: 800 • Efektif: 760</text>
                        <circle cx="250" cy="140" r="32" fill="#4338ca" fill-opacity="0.4"/>
                        <text x="250" y="145" fill="#ffffff" font-size="13" font-weight="bold" font-family="monospace" text-anchor="middle">LIVE 68%</text>
                    </g>

                    <!-- Zona 2: VIP Deck & Lounge -->
                    <g id="zone-svg-2" class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="440" y="30" width="220" height="200" rx="12" fill="#064e3b" stroke="#10b981" stroke-width="2" fill-opacity="0.6"/>
                        <text x="550" y="65" fill="#d1fae5" font-size="13" font-weight="800" text-anchor="middle">VIP DECK & LOUNGE</text>
                        <text x="550" y="95" fill="#6ee7b7" font-size="11" font-family="monospace" text-anchor="middle">Kapasitas: 250</text>
                        <rect x="510" y="125" width="80" height="30" rx="6" fill="#065f46"/>
                        <text x="550" y="145" fill="#a7f3d0" font-size="11" font-weight="bold" font-family="monospace" text-anchor="middle">TABLES</text>
                    </g>

                    <!-- Zona 3: Open-Air Beach Front / Pool -->
                    <g id="zone-svg-3" class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="680" y="30" width="190" height="340" rx="12" fill="#0c4a6e" stroke="#0284c7" stroke-width="2" fill-opacity="0.5"/>
                        <text x="775" y="70" fill="#e0f2fe" font-size="12" font-weight="800" text-anchor="middle">OUTDOOR POOL</text>
                        <text x="775" y="95" fill="#7dd3fc" font-size="10" font-family="monospace" text-anchor="middle">WEATHER SENSITIVE</text>
                        <path d="M 720 180 Q 775 160 830 180 T 880 180" fill="none" stroke="#38bdf8" stroke-width="3" stroke-dasharray="4 4"/>
                        <text x="775" y="240" fill="#bae6fd" font-size="12" font-weight="bold" font-family="monospace" text-anchor="middle">KAPASITAS: 500</text>
                    </g>

                    <!-- Zona 4: Sunset Terrace -->
                    <g id="zone-svg-4" class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="80" y="250" width="340" height="120" rx="12" fill="#451a03" stroke="#f59e0b" stroke-width="2" fill-opacity="0.6"/>
                        <text x="250" y="285" fill="#fef3c7" font-size="13" font-weight="800" text-anchor="middle">SUNSET TERRACE</text>
                        <text x="250" y="310" fill="#fde68a" font-size="11" font-family="monospace" text-anchor="middle">Kapasitas: 300</text>
                    </g>

                    <!-- Zona 5: Central Bar & Food Court -->
                    <g id="zone-svg-5" class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="440" y="250" width="220" height="120" rx="12" fill="#312e81" stroke="#818cf8" stroke-width="2" fill-opacity="0.6"/>
                        <text x="550" y="285" fill="#ede9fe" font-size="13" font-weight="800" text-anchor="middle">BAR & LIQUOR SPOUT</text>
                        <text x="550" y="310" fill="#c4b5fd" font-size="11" font-family="monospace" text-anchor="middle">Kapasitas: 150</text>
                    </g>

                    <!-- Emergency Exits -->
                    <g>
                        <rect x="850" y="330" width="30" height="50" rx="4" fill="#991b1b" stroke="#f87171" stroke-width="1.5"/>
                        <text x="865" y="360" fill="#fee2e2" font-size="9" font-weight="bold" text-anchor="middle" transform="rotate(-90 865 360)">EXIT 1</text>
                    </g>
                </svg>
            </div>
        </div>

        <!-- Detail Tabel Zona & Aksi Operasional -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-200">Rincian Okupansi & Kontrol Gerbang Per Zona</h2>
                <span class="text-xs text-slate-500 font-mono">D89-4 • Algoritma V3 ZoneCapacityGovernor</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-950/60 text-slate-400 text-[11px] font-semibold uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Nama & Kode Zona</th>
                            <th class="px-6 py-3.5 text-center">Batas Hukum</th>
                            <th class="px-6 py-3.5 text-center">Kapasitas Efektif (V3)</th>
                            <th class="px-6 py-3.5 text-center">Okupansi Saat Ini</th>
                            <th class="px-6 py-3.5">Rasio Kepadatan</th>
                            <th class="px-6 py-3.5 text-center">Status Keamanan</th>
                            <th class="px-6 py-3.5 text-right">Aksi Intervensi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($zones as $z)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-100">{{ $z['name'] }}</div>
                                    <div class="text-xs font-mono text-slate-500">{{ $z['zone_code'] }} • {{ $z['venue_name'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums text-slate-300">
                                    {{ number_format($z['capacity_limit'], 0, ',', '.') }} orang
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs tabular-nums font-bold text-emerald-400">
                                    {{ number_format($z['effective_capacity'], 0, ',', '.') }} orang
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-sm tabular-nums font-extrabold text-white">
                                    {{ number_format($z['current_occupancy'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-24 bg-slate-800 rounded-full h-2 overflow-hidden">
                                            @php
                                                $barColor = match($z['risk_level']) {
                                                    'CRITICAL' => 'bg-rose-500',
                                                    'WARNING' => 'bg-amber-500',
                                                    default => 'bg-emerald-500',
                                                };
                                            @endphp
                                            <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ min(100, $z['occupancy_rate']) }}%"></div>
                                        </div>
                                        <span class="text-xs font-mono tabular-nums text-slate-300">{{ $z['occupancy_rate'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($z['crowd_lockdown_active'])
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-950 text-rose-400 border border-rose-800 animate-pulse">
                                            LOCKDOWN
                                        </span>
                                    @else
                                        @php
                                            $riskBadge = match($z['risk_level']) {
                                                'CRITICAL' => 'bg-rose-950 text-rose-400 border-rose-800',
                                                'WARNING' => 'bg-amber-950 text-amber-400 border-amber-800',
                                                'ELEVATED' => 'bg-cyan-950 text-cyan-400 border-cyan-800',
                                                default => 'bg-emerald-950 text-emerald-400 border-emerald-800',
                                            };
                                        @endphp
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $riskBadge }}">
                                            {{ $z['risk_level'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <form action="/venue/zones/{{ $z['id'] }}/lockdown" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg border {{ $z['crowd_lockdown_active'] ? 'bg-emerald-950 text-emerald-300 border-emerald-800 hover:bg-emerald-900' : 'bg-rose-950 text-rose-300 border-rose-800 hover:bg-rose-900' }}">
                                            {{ $z['crowd_lockdown_active'] ? 'Buka Kunci' : 'Kunci (Lockdown)' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-xs text-slate-500 italic">
                                    Belum ada zona venue yang terdaftar dalam sistem pemantauan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>

