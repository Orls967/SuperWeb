<x-ds.card class="bg-slate-800/80 border-slate-700/60 p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span>Skor Komposit Kesehatan Sistem Pencarian (Search Health Index)</span>
        </h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
            STATUS: EXCELLENT (96.4/100)
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 pt-2">
        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span class="text-slate-400">Latensi Respon</span>
                <span class="text-emerald-400 font-bold">100 / 100</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 100%"></div>
            </div>
            <p class="text-[10px] text-slate-500">P95 &lt; 30ms aman</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span class="text-slate-400">Rasio Temu Balik</span>
                <span class="text-emerald-400 font-bold">95 / 100</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 95%"></div>
            </div>
            <p class="text-[10px] text-slate-500">0-Result &lt; 2.5%</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span class="text-slate-400">Kepadatan Term</span>
                <span class="text-emerald-400 font-bold">92 / 100</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 92%"></div>
            </div>
            <p class="text-[10px] text-slate-500">Rata-rata 18 term/dokumen</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span class="text-slate-400">Kebaruan Indeks</span>
                <span class="text-emerald-400 font-bold">98 / 100</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 98%"></div>
            </div>
            <p class="text-[10px] text-slate-500">Update waktu nyata aktif</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800 space-y-2">
            <div class="flex justify-between items-center text-xs">
                <span class="text-slate-400">Integritas Dua Sumber</span>
                <span class="text-emerald-400 font-bold">100 / 100</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 100%"></div>
            </div>
            <p class="text-[10px] text-slate-500">0 Dangling posting terms</p>
        </div>
    </div>
</x-ds.card>

