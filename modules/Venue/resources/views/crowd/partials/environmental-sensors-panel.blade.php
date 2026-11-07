<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="environmentalSensorsPanelComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-rose-500/20 text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
                Telemetri Lingkungan Panggung, Suhu & Kualitas Udara (CO2)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Pemantauan konsentrasi gas CO2 dan indeks panas basah (Wet Bulb Globe Temperature) di ruang tertutup.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Sensor Udara: AMAN
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <span class="text-slate-400 block text-[11px]">Konsentrasi CO2 (Indoor):</span>
            <span class="text-xl font-bold font-mono text-emerald-400">620 ppm</span>
            <p class="text-slate-500 text-[10px]">Batas bahaya: &gt; 1.200 ppm (Ventilasi HVAC Otomatis 100%)</p>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <span class="text-slate-400 block text-[11px]">Indeks Panas (WBGT):</span>
            <span class="text-xl font-bold font-mono text-amber-400">28.4 °C</span>
            <p class="text-slate-500 text-[10px]">Stasiun hidrasi gratis aktif membagikan air mineral</p>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-1">
            <span class="text-slate-400 block text-[11px]">Tingkat Kebisingan Rata-rata:</span>
            <span class="text-xl font-bold font-mono text-white">94.8 dBA</span>
            <p class="text-slate-500 text-[10px]">Peredam akustik aktif menjaga keselamatan pendengaran kru panggung</p>
        </div>
    </div>
</div>

<script>
function environmentalSensorsPanelComponent() {
    return {
        isHealthy: true
    }
}
</script>
