@props([
    'activeTier' => 'GOLD',
])

<div x-data="{ openModal: false }" class="inline-block">
    <button type="button" @click="openModal = true"
            class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline flex items-center space-x-1">
        <span>Bandingkan Hak Istimewa Tier</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </button>

    <div x-show="openModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 text-left"
             @click.away="openModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Matriks Perbandingan Hak Istimewa Tier Global</h3>
                <button type="button" @click="openModal = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="overflow-x-auto text-xs">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 font-bold">
                            <th class="py-2.5 px-3">Fasilitas / Hak</th>
                            <th class="py-2.5 px-3 text-center">Silver</th>
                            <th class="py-2.5 px-3 text-center text-amber-500">Gold</th>
                            <th class="py-2.5 px-3 text-center text-cyan-400">Platinum</th>
                            <th class="py-2.5 px-3 text-center text-amber-400">Black</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td class="py-2 px-3 font-semibold">Diskon Poin Award Night</td>
                            <td class="py-2 px-3 text-center text-slate-400">0%</td>
                            <td class="py-2 px-3 text-center font-bold text-amber-500">5%</td>
                            <td class="py-2 px-3 text-center font-bold text-cyan-400">10%</td>
                            <td class="py-2 px-3 text-center font-bold text-amber-400">15%</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-semibold">Late Check-Out</td>
                            <td class="py-2 px-3 text-center text-slate-400">-</td>
                            <td class="py-2 px-3 text-center font-semibold">14:00 WITA</td>
                            <td class="py-2 px-3 text-center font-semibold">16:00 WITA</td>
                            <td class="py-2 px-3 text-center font-bold text-amber-400">24 Jam Penuh</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-semibold">Upgrade Kamar Terbaik</td>
                            <td class="py-2 px-3 text-center text-slate-400">-</td>
                            <td class="py-2 px-3 text-center text-slate-400">-</td>
                            <td class="py-2 px-3 text-center font-semibold">Tergantung Ketersediaan</td>
                            <td class="py-2 px-3 text-center font-bold text-emerald-500">Suite Dijamin</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-semibold">Akses VIP Konser Venue</td>
                            <td class="py-2 px-3 text-center text-slate-400">-</td>
                            <td class="py-2 px-3 text-center text-slate-400">-</td>
                            <td class="py-2 px-3 text-center font-semibold">Fast-Track Antrean</td>
                            <td class="py-2 px-3 text-center font-bold text-emerald-500">Lounge VIP + 1 Tamu</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" @click="openModal = false" class="px-4 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs hover:bg-slate-800">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

