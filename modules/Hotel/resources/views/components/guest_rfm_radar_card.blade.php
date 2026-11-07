@props(['profile' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Radar Profil Tamu & Segmentasi RFM Multi-Properti
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Metrik Recency, Frequency, Monetary (RFM), LTV proyeksi, serta analisis sentimen interaksi tamu.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 rounded-full">
            Passport VIP Tier
        </span>
    </div>

    <!-- RFM Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recency (R)</div>
            <div class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">18 Hari Lalu</div>
            <p class="text-xs text-emerald-600 mt-1">Tergolong Sangat Aktif</p>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Frequency (F)</div>
            <div class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">14 Malam / Thn</div>
            <p class="text-xs text-indigo-600 mt-1">Target Platinum: 25 Malam</p>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Monetary (M)</div>
            <div class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">Rp 48.500.000</div>
            <p class="text-xs text-slate-500 mt-1">ADR rata-rata: Rp 3.460.000</p>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">LTV Estimasi 3-Tahun</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">Rp 185.000.000</div>
            <p class="text-xs text-slate-500 mt-1">Probabilitas Churn: 8.5% (Low)</p>
        </div>
    </div>

    <!-- Preferensi & Minat Tamu -->
    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase">Preferensi Personal & Pantangan Tamu:</div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="px-2.5 py-1 rounded-md bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">Bantal Bulu Angsa Hypoallergenic</span>
            <span class="px-2.5 py-1 rounded-md bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">Suhu Kamar 21.5°C</span>
            <span class="px-2.5 py-1 rounded-md bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">Lantai Tinggi (Quiet Zone)</span>
            <span class="px-2.5 py-1 rounded-md bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 font-medium">Alergi Kacang Tanah & Seafood Mentah</span>
            <span class="px-2.5 py-1 rounded-md bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">Sparkling Water saat Turn-down</span>
        </div>
    </div>
</div>
