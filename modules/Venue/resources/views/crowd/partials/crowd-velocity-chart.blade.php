<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="crowdVelocityChartComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping"></span>
                Aliran Vektor Kecepatan & Turbulensi Massa (Optical Flow)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Analisis gradien kecepatan massa untuk deteksi dini gelombang kejut kerumunan (shockwaves).</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-md text-[11px] font-mono bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                Laju Rata-rata: <strong class="text-white">0.42 m/s</strong>
            </span>
            <span class="px-2.5 py-1 rounded-md text-[11px] font-mono bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                Turbulensi: <strong class="text-emerald-400">0.18 (Laminar)</strong>
            </span>
        </div>
    </div>

    {{-- Interactive SVG Optical Flow Vectors Canvas --}}
    <div class="relative w-full aspect-[21/9] bg-slate-900 rounded-xl border border-slate-800 overflow-hidden flex items-center justify-center">
        <svg viewBox="0 0 700 300" class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <marker id="arrowLaminar" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#38bdf8"/>
                </marker>
                <marker id="arrowTurbulent" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#f43f5e"/>
                </marker>
            </defs>

            {{-- Flow vector lines --}}
            <g stroke="#38bdf8" stroke-width="2" marker-end="url(#arrowLaminar)" opacity="0.8">
                <line x1="80" y1="80" x2="160" y2="80" />
                <line x1="80" y1="150" x2="170" y2="150" />
                <line x1="80" y1="220" x2="150" y2="220" />

                <line x1="220" y1="75" x2="310" y2="70" />
                <line x1="230" y1="150" x2="320" y2="150" />
                <line x1="210" y1="225" x2="300" y2="230" />

                <line x1="370" y1="70" x2="460" y2="65" />
                <line x1="380" y1="150" x2="470" y2="150" />
                <line x1="360" y1="230" x2="450" y2="235" />

                <line x1="520" y1="65" x2="610" y2="60" />
                <line x1="530" y1="150" x2="620" y2="150" />
                <line x1="510" y1="235" x2="600" y2="240" />
            </g>

            {{-- Chokepoint Alert Icon --}}
            <g transform="translate(480, 130)">
                <rect width="140" height="40" rx="8" fill="#1e293b" stroke="#38bdf8" stroke-width="1.5" />
                <text x="70" y="24" fill="#bae6fd" font-size="11" font-weight="bold" text-anchor="middle">Gerbang Exit Timur</text>
            </g>
        </svg>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-300">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
            <span class="text-slate-400 block text-[11px]">Kecepatan Egress Bebas:</span>
            <strong class="text-white font-mono">1.2 m/detik (NFPA 101 Standard)</strong>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
            <span class="text-slate-400 block text-[11px]">Kecepatan Riil Saat Ini:</span>
            <strong class="text-amber-400 font-mono">0.42 m/detik (Penyempitan 65%)</strong>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-3">
            <span class="text-slate-400 block text-[11px]">Status Regim Aliran:</span>
            <strong class="text-emerald-400 font-mono">Laminar Teratur</strong>
        </div>
    </div>
</div>

<script>
function crowdVelocityChartComponent() {
    return {
        refreshRate: 5000
    }
}
</script>
