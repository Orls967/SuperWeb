<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="bartenderShiftModalComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-amber-500/20 text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
                Alokasi Penugasan Shift & Roster Bartender Cadangan
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Penugasan cepat personil shift HCM ke pos layanan bar yang mengalami saturasi antrean.</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-sky-500/20 text-sky-300 border border-sky-500/40">
            Cadangan Standby: 6 Orang
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Bar Stasiun 03 (Pavilion Utara)</span>
                <span class="text-rose-400 font-mono font-bold text-[11px]">Saturasi 72s</span>
            </div>
            <p class="text-slate-400 text-[11px]">Rekomendasi Algoritma V15: Tambah 2 Bartender untuk menurunkan waktu antrean ke 35 detik.</p>
            <button type="button" @click="dispatchStaff(3, 2)" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg transition-colors text-xs">
                Tugaskan 2 Bartender Sekarang &rarr;
            </button>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-white">Bar Stasiun 01 (Main Stage)</span>
                <span class="text-emerald-400 font-mono font-bold text-[11px]">Stabil 54s</span>
            </div>
            <p class="text-slate-400 text-[11px]">Kapasitas memadai, 5 staf aktif melayani pemesanan minuman festival.</p>
            <button type="button" disabled class="w-full py-2 bg-slate-800 text-slate-500 font-semibold rounded-lg text-xs cursor-not-allowed">
                Kapasitas Optimal
            </button>
        </div>
    </div>
</div>

<script>
function bartenderShiftModalComponent() {
    return {
        dispatchStaff(stationId, count) {
            alert('Penugasan ' + count + ' bartender ke Stasiun ' + stationId + ' berhasil diproses.');
        }
    }
}
</script>
