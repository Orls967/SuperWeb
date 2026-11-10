<div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <h3 class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center space-x-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span>Matriks Siklus Hidup Index Job (8 Status)</span>
        </h3>
        <span class="text-[11px] font-mono text-slate-400">Idempotency Guaranteed</span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-slate-400 block text-[10px] uppercase font-bold">1. Pending</span>
            <span class="text-slate-200 mt-1 block">Inisialisasi antrean</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-slate-400 block text-[10px] uppercase font-bold">2. Queued</span>
            <span class="text-slate-200 mt-1 block">Terkunci di queue worker</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-blue-400 block text-[10px] uppercase font-bold">3. Processing</span>
            <span class="text-slate-200 mt-1 block">Ekstraksi token aktif</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-indigo-400 block text-[10px] uppercase font-bold">4. Reindexing</span>
            <span class="text-slate-200 mt-1 block">Penyusunan posting list</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-emerald-400 block text-[10px] uppercase font-bold">5. Indexed</span>
            <span class="text-slate-200 mt-1 block">Selesai &amp; terverifikasi</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-amber-400 block text-[10px] uppercase font-bold">6. Degraded</span>
            <span class="text-slate-200 mt-1 block">Peringatan parsial</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-rose-400 block text-[10px] uppercase font-bold">7. Failed</span>
            <span class="text-slate-200 mt-1 block">Kegagalan kritis &amp; rollback</span>
        </div>
        <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800">
            <span class="text-purple-400 block text-[10px] uppercase font-bold">8. Archived</span>
            <span class="text-slate-200 mt-1 block">Diarsipkan ke riwayat</span>
        </div>
    </div>
</div>

