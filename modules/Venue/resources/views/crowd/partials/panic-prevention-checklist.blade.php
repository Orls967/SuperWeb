<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="panicPreventionChecklistComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-amber-500/20 text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                Protokol Kesiapsiagaan Pra-Insiden & Pencegahan Panic Rush
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Daftar periksa operasional marshals dan crowd safety sebelum pintu dibuka dan selama surge panggung.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
            Kesiapan: 100% Lolos
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Barikade Mojo & Front-of-Stage</span>
                <span class="text-emerald-400 font-mono text-[11px]">TERKUNCI & KOKOH</span>
            </div>
            <p class="text-slate-400 text-[11px]">Pin pengait barikade panggung terpasang ganda dengan bantalan peredam desakan dada.</p>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Lampu Darurat & Emergency Signage</span>
                <span class="text-emerald-400 font-mono text-[11px]">BATERAI 100%</span>
            </div>
            <p class="text-slate-400 text-[11px]">Petunjuk arah evakuasi hijau menyala terang dengan pasokan daya UPS terpisah 4 jam.</p>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Jalur Akses Khusus Paramedis</span>
                <span class="text-emerald-400 font-mono text-[11px]">BEBAS HAMBATAN</span>
            </div>
            <p class="text-slate-400 text-[11px]">Koridor steril selebar 2 meter dari pos medis tenda ke area mosh pit tetap terjaga.</p>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Sistem PA Audio Voice Alarm</span>
                <span class="text-emerald-400 font-mono text-[11px]">TERUJI SUARA JERNIH</span>
            </div>
            <p class="text-slate-400 text-[11px]">Pesan evakuasi rekaman dwibahasa (Indonesia - Inggris) terkonfigurasi auto-cut suara panggung.</p>
        </div>
    </div>
</div>

<script>
function panicPreventionChecklistComponent() {
    return {
        isReady: true
    }
}
</script>
