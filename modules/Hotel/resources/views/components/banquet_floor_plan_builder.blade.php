@props(['event' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Layout Tata Ruang (Floor Plan) & Koordinasi Banquet Kitchen
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pengaturan penempatan meja bundar (round table), panggung VIP, buffet line, serta alokasi staf pelayanan.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 rounded-full">
                Kapasitas: 450 Pax (Grand Ballroom)
            </span>
        </div>
    </div>

    <!-- Visual Layout Preview Canvas -->
    <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-8 bg-slate-50 dark:bg-slate-800/40 relative min-h-[300px] flex flex-col justify-between">
        <!-- Stage Area -->
        <div class="w-full bg-slate-800 text-white text-center py-2 rounded-lg font-bold text-xs uppercase tracking-widest shadow">
            Panggung Utama & LED Screen 12x4 Meter
        </div>

        <!-- Tables Grid Simulation -->
        <div class="grid grid-cols-5 gap-6 my-8 place-items-center">
            @for ($i = 1; $i <= 10; $i++)
                <div class="flex flex-col items-center group cursor-pointer">
                    <div class="w-14 h-14 rounded-full border-2 border-indigo-500 bg-indigo-50 dark:bg-indigo-950 flex items-center justify-center font-bold text-xs text-indigo-700 dark:text-indigo-300 group-hover:scale-110 transition shadow-sm">
                        T-{{ sprintf('%02d', $i) }}
                    </div>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium">10 Pax (Set Menu)</span>
                </div>
            @endfor
        </div>

        <!-- Buffet & Service Line -->
        <div class="flex justify-between items-center pt-4 border-t border-slate-200 dark:border-slate-700 text-xs">
            <div class="px-4 py-2 bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 rounded font-semibold">
                Buffet Line A (Appetizer & Main Course)
            </div>
            <div class="px-4 py-2 bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300 rounded font-semibold">
                Beverage Bar & Live Station Coffee
            </div>
            <div class="px-4 py-2 bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 rounded font-semibold">
                Buffet Line B (Dessert & Carving Stall)
            </div>
        </div>
    </div>

    <!-- Production Kitchen Sync Status -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="text-slate-400 font-medium">Kesiapan Butchery & Bahan Pokok</div>
            <div class="font-bold text-emerald-600 dark:text-emerald-400 mt-1 text-sm">100% Dialokasikan (Recipe BOM)</div>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="text-slate-400 font-medium">Alokasi Banquet Waitstaff (HCM Gig)</div>
            <div class="font-bold text-indigo-600 dark:text-indigo-400 mt-1 text-sm">24 Staf Terjadwal (K18 Bounty)</div>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="text-slate-400 font-medium">Target Service Time Platting</div>
            <div class="font-bold text-amber-600 dark:text-amber-400 mt-1 text-sm">18:45 WITA (Waktu Saji)</div>
        </div>
    </div>
</div>
