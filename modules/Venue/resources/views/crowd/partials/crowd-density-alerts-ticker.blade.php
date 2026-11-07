<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="crowdDensityAlertsTickerComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-rose-500/20 text-rose-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </span>
                Notifikasi Ticker Otomatis Deteksi Ancaman Massa (Surge Alerts)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Umpan siaran notifikasi peringatan dini dari algoritma CrowdDensitySurgePredictor (V14).</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 animate-pulse">
            1 Peringatan Aktif
        </span>
    </div>

    <div class="space-y-2">
        <div class="p-3 bg-rose-950/40 border border-rose-500/40 rounded-xl flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 mt-1 flex-shrink-0 animate-ping"></span>
                <div>
                    <span class="text-xs font-bold text-white block">Main Stage: Prediksi Lonjakan Kepadatan 15 Menit ke Depan</span>
                    <p class="text-[11px] text-rose-200 mt-0.5">Laju masuk melebihi 45 orang/menit, densitas diproyeksikan menembus 4.5 org/m² saat penampilan artis utama.</p>
                </div>
            </div>
            <span class="text-[11px] font-mono text-slate-400 flex-shrink-0">21:44:12</span>
        </div>

        <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 mt-1 flex-shrink-0"></span>
                <div>
                    <span class="text-xs font-bold text-white block">Gerbang Timur: Laju Egress Normal Sesuai Kapasitas</span>
                    <p class="text-[11px] text-slate-400 mt-0.5">Arus keluar tamu di gerbang timur berjalan lancar tanpa indikasi penumpukan.</p>
                </div>
            </div>
            <span class="text-[11px] font-mono text-slate-400 flex-shrink-0">21:40:00</span>
        </div>
    </div>
</div>

<script>
function crowdDensityAlertsTickerComponent() {
    return {
        alertsCount: 1
    }
}
</script>
