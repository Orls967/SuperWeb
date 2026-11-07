@props(['roster' => []])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Jadwal & Utilisasi Terapis Spa & Wellness
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pemetaan slot ketersediaan terapis, ruang treatment, dan cross-charge otomatis ke folio tamu.</p>
        </div>
        <div class="flex items-center gap-2">
            <button class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-teal-600 hover:bg-teal-700 text-white transition shadow-sm">
                + Reservasi Treatment Baru
            </button>
        </div>
    </div>

    <!-- Roster Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Terapis 1 -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">Ni Luh Putu (Senior)</div>
                <span class="px-2 py-0.5 text-[10px] rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-semibold">Tersedia</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">Spesialisasi: Balinese Deep Tissue, Herbal Aromatherapy</div>
            <div class="space-y-1.5 text-xs">
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>10:00 - 11:30</span>
                    <span class="font-medium">Suite 302 (Paid Folio)</span>
                </div>
                <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded text-slate-500 dark:text-slate-400 flex justify-between">
                    <span>14:00 - 15:30</span>
                    <span class="font-medium text-teal-600">Slot Terbuka</span>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>16:30 - 18:00</span>
                    <span class="font-medium">Villa 08 (Walk-in)</span>
                </div>
            </div>
        </div>

        <!-- Terapis 2 -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">Wayan Sari (Aroma)</div>
                <span class="px-2 py-0.5 text-[10px] rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 font-semibold">In Treatment</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">Spesialisasi: Hot Stone, Shirodhara Ayurvedic</div>
            <div class="space-y-1.5 text-xs">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 rounded text-indigo-800 dark:text-indigo-300 flex justify-between">
                    <span>09:30 - 11:00</span>
                    <span class="font-medium">Chamber B (In Progress)</span>
                </div>
                <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded text-slate-500 dark:text-slate-400 flex justify-between">
                    <span>13:00 - 14:30</span>
                    <span class="font-medium text-teal-600">Slot Terbuka</span>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>15:30 - 17:00</span>
                    <span class="font-medium">Room 504 (Corp Bill)</span>
                </div>
            </div>
        </div>

        <!-- Terapis 3 -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">Made Artha (Reflexology)</div>
                <span class="px-2 py-0.5 text-[10px] rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-semibold">Tersedia</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">Spesialisasi: Foot Reflexology, Acupressure</div>
            <div class="space-y-1.5 text-xs">
                <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded text-slate-500 dark:text-slate-400 flex justify-between">
                    <span>10:30 - 12:00</span>
                    <span class="font-medium text-teal-600">Slot Terbuka</span>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>14:00 - 15:30</span>
                    <span class="font-medium">Cabana Pool 2</span>
                </div>
                <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded text-slate-500 dark:text-slate-400 flex justify-between">
                    <span>17:00 - 18:30</span>
                    <span class="font-medium text-teal-600">Slot Terbuka</span>
                </div>
            </div>
        </div>

        <!-- Terapis 4 -->
        <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-slate-800 dark:text-slate-100 text-sm">Ketut Dewi (Body Scrub)</div>
                <span class="px-2 py-0.5 text-[10px] rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-semibold">Istirahat</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">Spesialisasi: Javanese Lulur, Coconut Scrub</div>
            <div class="space-y-1.5 text-xs">
                <div class="p-2 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded text-amber-800 dark:text-amber-300 flex justify-between">
                    <span>12:00 - 13:30</span>
                    <span class="font-medium">Shift Break & Prep</span>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>14:30 - 16:00</span>
                    <span class="font-medium">Pavilion Rose</span>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded text-emerald-800 dark:text-emerald-300 flex justify-between">
                    <span>16:30 - 18:00</span>
                    <span class="font-medium">Suite 401 (Package)</span>
                </div>
            </div>
        </div>
    </div>
</div>
