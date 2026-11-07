<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="crowdDensityGaugeComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-purple-500/20 text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                Spektogram Densitas Spasial & Ambang Batas Desakan
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Pemetaan kuantitatif Fruin Level of Service (LoS A sampai F) per meter persegi area lantai.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-500/40">
            Fruin Status: LoS D (Padat)
        </span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 text-center text-xs">
        <div class="p-2.5 bg-slate-900 border border-emerald-500/30 rounded-xl">
            <span class="text-emerald-400 font-bold block text-sm">LoS A</span>
            <span class="text-slate-400 text-[10px]">&lt; 0.5 org/m²</span>
            <span class="text-[10px] text-emerald-400 block mt-1">Sangat Bebas</span>
        </div>
        <div class="p-2.5 bg-slate-900 border border-emerald-500/20 rounded-xl">
            <span class="text-emerald-300 font-bold block text-sm">LoS B</span>
            <span class="text-slate-400 text-[10px]">0.5 - 1.0 org/m²</span>
            <span class="text-[10px] text-emerald-300 block mt-1">Leluasa</span>
        </div>
        <div class="p-2.5 bg-slate-900 border border-sky-500/20 rounded-xl">
            <span class="text-sky-300 font-bold block text-sm">LoS C</span>
            <span class="text-slate-400 text-[10px]">1.0 - 1.8 org/m²</span>
            <span class="text-[10px] text-sky-300 block mt-1">Lancar</span>
        </div>
        <div class="p-2.5 bg-slate-900 border border-amber-500/30 rounded-xl">
            <span class="text-amber-400 font-bold block text-sm">LoS D</span>
            <span class="text-slate-400 text-[10px]">1.8 - 2.5 org/m²</span>
            <span class="text-[10px] text-amber-400 block mt-1">Padat Teratur</span>
        </div>
        <div class="p-2.5 bg-slate-900 border border-orange-500/30 rounded-xl">
            <span class="text-orange-400 font-bold block text-sm">LoS E</span>
            <span class="text-slate-400 text-[10px]">2.5 - 3.8 org/m²</span>
            <span class="text-[10px] text-orange-400 block mt-1">Sangat Padat</span>
        </div>
        <div class="p-2.5 bg-slate-900 border border-rose-500/50 rounded-xl animate-pulse">
            <span class="text-rose-400 font-bold block text-sm">LoS F</span>
            <span class="text-slate-400 text-[10px]">&gt; 3.8 org/m²</span>
            <span class="text-[10px] text-rose-400 block mt-1 font-bold">Bahaya Desakan</span>
        </div>
    </div>
</div>

<script>
function crowdDensityGaugeComponent() {
    return {
        level: 'D'
    }
}
</script>
