<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="egressRouteGuidanceComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-emerald-500/20 text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </span>
                Panduan Rute Evakuasi Dinamis (ZoneEmergencyEgressPathfinder)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Rute terpendek dan terbebas dari chokepoint kepadatan tinggi ke titik kumpul aman.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Jalur Terpilih: EXIT GATE 04
        </span>
    </div>

    {{-- Waypoint Sequence List --}}
    <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-700">
        <div class="relative flex items-start gap-3">
            <span class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-slate-900 bg-rose-500 flex items-center justify-center">
                <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
            </span>
            <div>
                <span class="text-xs font-bold text-white block">Titik Awal: Zona Main Stage Mosh Pit</span>
                <p class="text-[11px] text-slate-400">Densitas saat ini 4.2 org/m². Hindari bergerak ke koridor barat karena penumpukan bar.</p>
            </div>
        </div>

        <div class="relative flex items-start gap-3">
            <span class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-slate-900 bg-amber-400 flex items-center justify-center">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-900"></span>
            </span>
            <div>
                <span class="text-xs font-bold text-white block">Waypoint 1: Koridor Timur Samping (Lebar 4.5 Meter)</span>
                <p class="text-[11px] text-slate-400">Kapasitas debit 328 orang/menit. Petugas keamanan B-04 bersiaga mengarahkan arus.</p>
            </div>
        </div>

        <div class="relative flex items-start gap-3">
            <span class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-slate-900 bg-emerald-400 flex items-center justify-center">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-900"></span>
            </span>
            <div>
                <span class="text-xs font-bold text-white block">Tujuan Akhir: Assembly Point Lapangan Terbuka Timur (Exit 4)</span>
                <p class="text-[11px] text-emerald-400 font-medium">Bebas hambatan, gerbang turnstile otomatis terbuka rotasi bebas.</p>
            </div>
        </div>
    </div>

    <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
        <span>Total Jarak: <strong class="text-white font-mono">68 Meter</strong></span>
        <span>Estimasi Waktu Evakuasi: <strong class="text-emerald-400 font-mono">56 Detik</strong></span>
        <button type="button" class="text-sky-400 hover:text-sky-300 font-semibold hover:underline">
            Kirim Notifikasi ke Aplikasi Tamu &rarr;
        </button>
    </div>
</div>

<script>
function egressRouteGuidanceComponent() {
    return {
        activeRoute: 'EXIT-4'
    }
}
</script>
