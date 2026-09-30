<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('Jaringan & Titik Logistik (Sari Ranah Express)') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('logistics.lanes.index') }}" class="px-4 py-2 text-sm font-medium rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 transition-all">
                    Kelola Jalur (Lanes)
                </a>
                <a href="{{ route('logistics.locations.create') }}" class="px-4 py-2 text-sm font-medium rounded-xl bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/25 transition-all">
                    + Tambah Lokasi
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 space-y-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Flash Notification --}}
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- SVG Schematic Network Map --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-700/50 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        Peta Skematik Jaringan Multimoda Berpusat di Banjarmasin Hub
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Visualisasi interaktif jalur darat (trucking), laut (vessel), dan udara (freighter) lintas regional dan internasional.</p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-1 bg-amber-400 rounded"></span> Darat</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-1 bg-cyan-400 rounded"></span> Laut</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-1 bg-purple-400 rounded"></span> Udara</span>
                </div>
            </div>

            @php
                // Pre-map schematic 2D coordinates for clear SVG visualization
                $coords = [
                    // International / Far
                    'PORT-CNSHA' => [910, 50],
                    'AIRP-PVG'   => [940, 75],
                    'PORT-SGSIN' => [360, 160],
                    'AIRP-SIN'   => [380, 185],
                    // Western Indonesia
                    'PORT-IDBLW' => [130, 120],
                    'AIRP-KNO'   => [150, 145],
                    'PORT-IDTPP' => [320, 480],
                    'AIRP-CGK'   => [340, 510],
                    'DEP-TPP'    => [300, 530],
                    // East Java
                    'PORT-IDSUB' => [500, 510],
                    'AIRP-SUB'   => [520, 540],
                    'DEP-SUB'    => [480, 530],
                    // South Sulawesi
                    'PORT-IDMAK' => [760, 430],
                    'AIRP-UPG'   => [780, 455],
                    // Kalimantan Core (Centered around Banjarmasin)
                    'PORT-IDBDJ' => [520, 320],
                    'AIRP-BDJ'   => [560, 360],
                    'HUB-BDJ'    => [540, 335],
                    'CFS-BDJ'    => [505, 335],
                    'DEP-BDJ'    => [515, 355],
                    'HUB-BJB'    => [585, 340],
                    'HUB-MTP'    => [615, 330],
                    'HUB-PKY'    => [470, 240],
                    'HUB-BPN'    => [670, 230],
                    'AIRP-BPN'   => [690, 255],
                    'HUB-SMD'    => [690, 170],
                ];
            @endphp

            <div class="relative w-full overflow-x-auto bg-slate-900/80 rounded-xl border border-slate-700/50 p-2">
                <svg viewBox="0 0 1000 580" class="w-full h-auto min-w-[750px] font-sans select-none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- Grid pattern -->
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#334155" stroke-width="0.5" opacity="0.3"/>
                        </pattern>
                        <!-- Glow filter -->
                        <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>

                    <rect width="1000" height="580" fill="#0f172a" />
                    <rect width="1000" height="580" fill="url(#grid)" />

                    <!-- Background Region Labels -->
                    <text x="120" y="80" fill="#475569" font-size="11" font-weight="bold" letter-spacing="1">SUMATERA</text>
                    <text x="320" y="440" fill="#475569" font-size="11" font-weight="bold" letter-spacing="1">JAWA BARAT</text>
                    <text x="500" y="470" fill="#475569" font-size="11" font-weight="bold" letter-spacing="1">JAWA TIMUR</text>
                    <text x="550" y="160" fill="#64748b" font-size="13" font-weight="bold" letter-spacing="2">KALIMANTAN (CENTRAL HUB)</text>
                    <text x="760" y="380" fill="#475569" font-size="11" font-weight="bold" letter-spacing="1">SULAWESI</text>
                    <text x="360" y="120" fill="#475569" font-size="10" font-weight="bold" letter-spacing="1">SINGAPORE</text>
                    <text x="880" y="30" fill="#475569" font-size="10" font-weight="bold" letter-spacing="1">SHANGHAI, CN</text>

                    <!-- Lanes (Lines) -->
                    @foreach($lanes as $lane)
                        @php
                            $p1 = $coords[$lane->origin->code] ?? null;
                            $p2 = $coords[$lane->destination->code] ?? null;
                            if (!$p1 || !$p2) continue;

                            $color = match($lane->mode->value) {
                                'road' => '#fbbf24',
                                'sea' => '#22d3ee',
                                'air' => '#c084fc',
                            };
                            $dash = match($lane->mode->value) {
                                'road' => 'none',
                                'sea' => '5,4',
                                'air' => '3,3',
                            };
                            $width = match($lane->mode->value) {
                                'road' => '1.8',
                                'sea' => '2',
                                'air' => '1.5',
                            };
                        @endphp
                        <line 
                            x1="{{ $p1[0] }}" y1="{{ $p1[1] }}" 
                            x2="{{ $p2[0] }}" y2="{{ $p2[1] }}" 
                            stroke="{{ $color }}" 
                            stroke-width="{{ $width }}" 
                            stroke-dasharray="{{ $dash }}" 
                            opacity="0.65"
                        >
                            <title>{{ $lane->origin->name }} → {{ $lane->destination->name }} ({{ $lane->mode->label() }}, {{ $lane->distanceKm() }} km)</title>
                        </line>
                    @endforeach

                    <!-- Nodes (Locations) -->
                    @foreach($allLocations as $loc)
                        @php
                            $pt = $coords[$loc->code] ?? null;
                            if (!$pt) continue;

                            $isCenter = str_contains($loc->code, 'BDJ');
                            $nodeColor = match($loc->type->value) {
                                'seaport' => '#06b6d4',
                                'airport' => '#a855f7',
                                'hub' => '#f59e0b',
                                'depot' => '#10b981',
                                'cfs' => '#6366f1',
                                default => '#3b82f6',
                            };
                            $r = $isCenter ? 7 : 5;
                        @endphp
                        <g class="cursor-pointer group" transform="translate({{ $pt[0] }}, {{ $pt[1] }})">
                            <title>{{ $loc->name }} [{{ $loc->code }}]&#10;{{ $loc->type->label() }}&#10;{{ $loc->city }}, {{ $loc->country }}</title>
                            @if($isCenter)
                                <circle r="12" fill="{{ $nodeColor }}" opacity="0.2" class="animate-pulse" />
                            @endif
                            <circle r="{{ $r }}" fill="{{ $nodeColor }}" stroke="#0f172a" stroke-width="2" filter="url(#glow)" />
                            <text y="{{ $r + 11 }}" x="0" text-anchor="middle" fill="#cbd5e1" font-size="9" font-weight="600" opacity="0.9">{{ $loc->iata ?? $loc->unlocode ?? $loc->code }}</text>
                        </g>
                    @endforeach
                </svg>
            </div>
        </div>

        {{-- Locations Table & Filters --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-slate-700/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-100">Daftar Titik Jaringan Logistik</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Total {{ $locations->total() }} titik terdaftar dalam jaringan multimoda.</p>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('logistics.locations.index') }}" class="flex flex-wrap items-center gap-3">
                    <input 
                        type="text" 
                        name="q" 
                        value="{{ $filters['q'] ?? '' }}" 
                        placeholder="Cari kode, nama, kota..." 
                        class="px-3 py-1.5 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />

                    <select name="type" class="px-3 py-1.5 text-xs rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Semua Tipe</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" {{ ($filters['type'] ?? '') === $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 transition-all">
                        Filter
                    </button>
                    @if(!empty($filters['q']) || !empty($filters['type']) || !empty($filters['country']))
                        <a href="{{ route('logistics.locations.index') }}" class="px-3 py-1.5 text-xs text-slate-400 hover:text-slate-200">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 font-semibold border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-6 py-3">Kode & Nama</th>
                            <th class="px-6 py-3">Tipe</th>
                            <th class="px-6 py-3">Identitas (UN/IATA)</th>
                            <th class="px-6 py-3">Wilayah</th>
                            <th class="px-6 py-3">Koordinat (e6)</th>
                            <th class="px-6 py-3">Zona Waktu</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @forelse($locations as $loc)
                            <tr class="hover:bg-slate-700/20 transition-all">
                                <td class="px-6 py-3.5">
                                    <div class="font-bold text-slate-100">{{ $loc->name }}</div>
                                    <div class="text-[11px] font-mono text-slate-400">{{ $loc->code }}</div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border
                                        @if($loc->type->value === 'seaport') bg-cyan-500/10 text-cyan-400 border-cyan-500/30
                                        @elseif($loc->type->value === 'airport') bg-violet-500/10 text-violet-400 border-violet-500/30
                                        @elseif($loc->type->value === 'hub') bg-amber-500/10 text-amber-400 border-amber-500/30
                                        @elseif($loc->type->value === 'depot') bg-emerald-500/10 text-emerald-400 border-emerald-500/30
                                        @else bg-blue-500/10 text-blue-400 border-blue-500/30
                                        @endif
                                    ">
                                        {{ $loc->type->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 font-mono text-slate-300">
                                    @if($loc->unlocode)
                                        <span class="px-1.5 py-0.5 rounded bg-slate-700/50 text-cyan-300 font-bold" title="UN/LOCODE">{{ $loc->unlocode }}</span>
                                    @endif
                                    @if($loc->iata)
                                        <span class="px-1.5 py-0.5 rounded bg-slate-700/50 text-purple-300 font-bold" title="IATA Code">{{ $loc->iata }}</span>
                                    @endif
                                    @if(!$loc->unlocode && !$loc->iata)
                                        <span class="text-slate-500">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5">
                                    <div>{{ $loc->city }}, {{ $loc->province }}</div>
                                    <span class="text-[10px] text-slate-500">{{ $loc->country }}</span>
                                </td>
                                <td class="px-6 py-3.5 font-mono text-[11px] text-slate-400">
                                    {{ number_format($loc->latitude(), 5) }}, {{ number_format($loc->longitude(), 5) }}
                                </td>
                                <td class="px-6 py-3.5 text-slate-300">
                                    {{ $loc->timezone }}
                                </td>
                                <td class="px-6 py-3.5 text-right space-x-2">
                                    <a href="{{ route('logistics.locations.edit', $loc) }}" class="text-blue-400 hover:text-blue-300 font-medium">Edit</a>
                                    <form method="POST" action="{{ route('logistics.locations.destroy', $loc) }}" class="inline" onsubmit="return confirm('Hapus lokasi ini dari jaringan?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-400 hover:text-rose-300 font-medium">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                    Tidak ada lokasi ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($locations->hasPages())
                <div class="p-4 border-t border-slate-700/50">
                    {{ $locations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
