<div class="relative bg-slate-950 rounded-2xl border border-slate-800 p-6 shadow-2xl overflow-hidden" x-data="interactiveHeatmapComponent()">
    {{-- Header / Toolbar --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 border-b border-slate-800 pb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center p-1.5 rounded-lg bg-sky-500/20 text-sky-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                </span>
                <h3 class="text-lg font-bold text-white tracking-wide">Peta Spasial & Heatmap Kepadatan Live</h3>
            </div>
            <p class="text-xs text-slate-400 mt-1">Telemetri LiDAR, Optical Flow & Gate Scanners. Memperbarui setiap 5 detik secara deterministik.</p>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                IoT Feed: ONLINE (4 Sensor)
            </span>
            <button @click="toggleGrid()" type="button" class="px-3 py-1.5 text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700 transition-colors">
                <span x-text="showGrid ? 'Sembunyikan Grid' : 'Tampilkan Grid'"></span>
            </button>
        </div>
    </div>

    {{-- Interactive SVG Venue Floor Plan --}}
    <div class="relative w-full aspect-[16/9] bg-slate-900/90 rounded-xl border border-slate-800/80 overflow-hidden flex items-center justify-center">
        <svg viewBox="0 0 1000 600" class="w-full h-full select-none" xmlns="http://www.w3.org/2000/svg">
            {{-- Background Grid Pattern --}}
            <defs>
                <pattern id="venueGrid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#1e293b" stroke-width="0.75"/>
                </pattern>
                <radialGradient id="emergencyGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#ef4444" stop-opacity="0.8"/>
                    <stop offset="100%" stop-color="#ef4444" stop-opacity="0"/>
                </radialGradient>
            </defs>

            <rect width="1000" height="600" fill="url(#venueGrid)" x-show="showGrid"/>

            {{-- Zone 1: Main Stage Mosh Pit --}}
            <g class="cursor-pointer transition-all duration-300 group" @click="selectZone('ZONE-STAGE', 'Main Festival Stage', 4.2, 850)">
                <polygon points="150,80 450,80 430,280 170,280" 
                         :class="selectedZone === 'ZONE-STAGE' ? 'stroke-cyan-400 stroke-[3]' : 'stroke-rose-500/80 stroke-[1.5]'"
                         class="fill-rose-950/60 transition-colors hover:fill-rose-900/70" />
                <text x="300" y="150" fill="#fca5a5" font-size="16" font-weight="bold" text-anchor="middle">Main Stage Area</text>
                <text x="300" y="175" fill="#f87171" font-size="12" font-mono text-anchor="middle">4.2 org/m² • 850 Tamu</text>
                <circle cx="300" cy="210" r="12" fill="#ef4444" class="animate-ping opacity-75"/>
                <circle cx="300" cy="210" r="6" fill="#ef4444"/>
            </g>

            {{-- Zone 2: VIP Lounge & Terrace --}}
            <g class="cursor-pointer transition-all duration-300 group" @click="selectZone('ZONE-VIP', 'VIP Lounge Terrace', 1.4, 210)">
                <polygon points="500,80 850,80 820,240 520,240" 
                         :class="selectedZone === 'ZONE-VIP' ? 'stroke-cyan-400 stroke-[3]' : 'stroke-emerald-500/80 stroke-[1.5]'"
                         class="fill-emerald-950/40 transition-colors hover:fill-emerald-900/50" />
                <text x="670" y="140" fill="#6ee7b7" font-size="16" font-weight="bold" text-anchor="middle">VIP Lounge & Terrace</text>
                <text x="670" y="165" fill="#34d399" font-size="12" font-mono text-anchor="middle">1.4 org/m² • 210 Tamu</text>
            </g>

            {{-- Zone 3: Central Food & Beverage Corridor --}}
            <g class="cursor-pointer transition-all duration-300 group" @click="selectZone('ZONE-BAR', 'Central F&B Pavilion', 2.8, 480)">
                <polygon points="180,320 800,320 780,440 200,440" 
                         :class="selectedZone === 'ZONE-BAR' ? 'stroke-cyan-400 stroke-[3]' : 'stroke-amber-500/80 stroke-[1.5]'"
                         class="fill-amber-950/40 transition-colors hover:fill-amber-900/50" />
                <text x="490" y="370" fill="#fde68a" font-size="16" font-weight="bold" text-anchor="middle">Central F&B Pavilion & Bars</text>
                <text x="490" y="395" fill="#fbbf24" font-size="12" font-mono text-anchor="middle">2.8 org/m² • 480 Tamu • WPS 78s</text>
            </g>

            {{-- Zone 4: Main Ingress / Turnstiles Hub --}}
            <g class="cursor-pointer transition-all duration-300 group" @click="selectZone('ZONE-GATES', 'Main Gate Turnstiles Hub', 1.8, 310)">
                <polygon points="250,480 730,480 700,560 280,560" 
                         :class="selectedZone === 'ZONE-GATES' ? 'stroke-cyan-400 stroke-[3]' : 'stroke-sky-500/80 stroke-[1.5]'"
                         class="fill-sky-950/40 transition-colors hover:fill-sky-900/50" />
                <text x="490" y="515" fill="#bae6fd" font-size="15" font-weight="bold" text-anchor="middle">Turnstiles Main Ingress (8 Gates)</text>
                <text x="490" y="535" fill="#38bdf8" font-size="11" font-mono text-anchor="middle">Face-ID Liveness Active • 28 org/min</text>
            </g>

            {{-- Emergency Egress Gate Icons --}}
            <g transform="translate(60, 160)">
                <rect width="60" height="40" rx="6" fill="#15803d" stroke="#4ade80" stroke-width="2"/>
                <text x="30" y="25" fill="#ffffff" font-size="11" font-weight="bold" text-anchor="middle">EXIT 1</text>
            </g>

            <g transform="translate(880, 160)">
                <rect width="60" height="40" rx="6" fill="#15803d" stroke="#4ade80" stroke-width="2"/>
                <text x="30" y="25" fill="#ffffff" font-size="11" font-weight="bold" text-anchor="middle">EXIT 2</text>
            </g>

            <g transform="translate(60, 360)">
                <rect width="60" height="40" rx="6" fill="#15803d" stroke="#4ade80" stroke-width="2"/>
                <text x="30" y="25" fill="#ffffff" font-size="11" font-weight="bold" text-anchor="middle">EXIT 3</text>
            </g>

            <g transform="translate(880, 360)">
                <rect width="60" height="40" rx="6" fill="#15803d" stroke="#4ade80" stroke-width="2"/>
                <text x="30" y="25" fill="#ffffff" font-size="11" font-weight="bold" text-anchor="middle">EXIT 4</text>
            </g>
        </svg>

        {{-- Floating Details Card on Zone Selection --}}
        <div x-show="selectedZone" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute top-4 right-4 bg-slate-900/95 border border-slate-700/80 rounded-xl p-4 shadow-2xl backdrop-blur-md w-72 space-y-2 pointer-events-auto"
             style="display: none;">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-sky-400 font-mono" x-text="selectedZone"></span>
                <button @click="selectedZone = null" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <h4 class="text-sm font-bold text-white" x-text="zoneTitle"></h4>
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-800 text-xs">
                <div>
                    <span class="text-slate-400 block">Kepadatan:</span>
                    <strong class="text-white font-mono" x-text="zoneDensity + ' org/m²'"></strong>
                </div>
                <div>
                    <span class="text-slate-400 block">Headcount:</span>
                    <strong class="text-white font-mono" x-text="zoneOccupants + ' orang'"></strong>
                </div>
            </div>
            <div class="pt-2">
                <button type="button" @click="dispatchGateDiversion()" class="w-full py-1.5 bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs rounded-lg transition-colors">
                    Alihkan Ingress Gerbang &rarr;
                </button>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center justify-center gap-6 mt-4 pt-4 border-t border-slate-800/80 text-xs text-slate-400">
        <span class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
            Aman Terbuka (&lt; 2.0 org/m²)
        </span>
        <span class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-sky-500/80"></span>
            Kepadatan Sedang (2.0 - 3.5 org/m²)
        </span>
        <span class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
            Padat Terkendali (3.5 - 4.0 org/m²)
        </span>
        <span class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></span>
            Bahaya Kritis (&gt; 4.0 org/m² - Intervensi Egress)
        </span>
    </div>
</div>

<script>
function interactiveHeatmapComponent() {
    return {
        showGrid: true,
        selectedZone: null,
        zoneTitle: '',
        zoneDensity: 0.0,
        zoneOccupants: 0,
        toggleGrid() {
            this.showGrid = !this.showGrid;
        },
        selectZone(code, title, density, occupants) {
            this.selectedZone = code;
            this.zoneTitle = title;
            this.zoneDensity = density;
            this.zoneOccupants = occupants;
        },
        dispatchGateDiversion() {
            alert('Perintah pengalihan gate dan penyesuaian laju masuk berhasil dikirimkan ke hardware turnstile.');
        }
    }
}
</script>
