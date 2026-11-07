@props(['beo' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Lembar Perintah Kapten Jamuan (Banquet Captain Order Sheet)
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Instruksi kronologis jalannya perjamuan gala dinner, jadwal plating dapur, koordinasi tata cahaya, dan briefing tim pramusaji.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 rounded-full">
            BEO ID: BEO-2026-092-VIP
        </span>
    </div>

    <!-- Timeline of Service Schedule -->
    <div class="space-y-3 text-xs">
        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 w-24 shrink-0">17:00 WITA</span>
            <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">Briefing Seluruh Pramusaji & Bar Team</div>
                <p class="text-slate-500 dark:text-slate-400 mt-0.5">Pemeriksaan kerapian seragam, penjelasan pantangan alergi meja VIP (Meja 1 & 2 bebas olahan kerang), alokasi stasiun meja 1 sd 10.</p>
            </div>
        </div>

        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 w-24 shrink-0">18:00 WITA</span>
            <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">Pintu Grand Ballroom Dibuka (Cocktail Reception)</div>
                <p class="text-slate-500 dark:text-slate-400 mt-0.5">Penyajian welcome drink mocktail markisa segar dan canape finger foods berjalan di area foyer.</p>
            </div>
        </div>

        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 w-24 shrink-0">19:00 WITA</span>
            <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">Plating Main Course Dimulai (Sinkronisasi Dapur)</div>
                <p class="text-slate-500 dark:text-slate-400 mt-0.5">Executive Chef mengomandoi plating simultan 450 porsi Tenderloin Wagyu dengan saus truffle. Pramusaji bersiap serentak di pintu keluar dapur.</p>
            </div>
        </div>

        <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <span class="font-mono font-bold text-amber-600 dark:text-amber-400 w-24 shrink-0">21:30 WITA</span>
            <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">Pembersihan Meja (Clear-Up) & Rekonsiliasi Tagihan Tambahan</div>
                <p class="text-slate-500 dark:text-slate-400 mt-0.5">Kapten menghitung konsumsi botol anggur ekstra dan lembar perpanjangan durasi sound system, meminta paraf penanggung jawab klien.</p>
            </div>
        </div>
    </div>
</div>
