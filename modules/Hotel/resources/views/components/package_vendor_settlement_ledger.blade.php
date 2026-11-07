@props(['settlements' => []])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Ledger Settlement Multi-Vendor Paket Wisata Terpadu
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rekonsiliasi escrow split berdasarkan Standalone Selling Price (SSP) untuk operator Hotel, Venue, dan Logistik.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-500 dark:text-slate-400">Total Escrow Aktif: <strong class="text-slate-800 dark:text-slate-100">Rp 175.000.000</strong></span>
            <button class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-purple-600 hover:bg-purple-700 text-white transition">
                Audit Batch Escrow
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">ID Booking / Paket</th>
                    <th class="px-4 py-3">Nama Tamu</th>
                    <th class="px-4 py-3 text-right">Nilai Paket</th>
                    <th class="px-4 py-3 text-right">Alokasi Hotel</th>
                    <th class="px-4 py-3 text-right">Alokasi Venue</th>
                    <th class="px-4 py-3 text-right">Alokasi Transport</th>
                    <th class="px-4 py-3 text-right">Sisa Escrow</th>
                    <th class="px-4 py-3 text-center">Status Pelepasan</th>
                    <th class="px-4 py-3 text-center">Aksi Milestone</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">PKG-BALI-3D2N-VIP</div>
                        <div class="text-[11px] text-slate-400">BKG-9299-A01</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Bpk. Hendrawan Santoso</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 12.500.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 7.500.000 (Released)</td>
                    <td class="px-4 py-3 text-right text-purple-600 font-medium">Rp 3.000.000 (Locked)</td>
                    <td class="px-4 py-3 text-right text-sky-600 font-medium">Rp 2.000.000 (Locked)</td>
                    <td class="px-4 py-3 text-right font-bold text-amber-600">Rp 5.000.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">PARTIAL (60%)</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="px-2.5 py-1 text-[11px] bg-purple-50 hover:bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 rounded font-medium transition">Release Venue</button>
                    </td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">PKG-LOMBOK-SUNSET</div>
                        <div class="text-[11px] text-slate-400">BKG-9304-B12</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Ibu Maya Anggraeni</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 8.900.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 5.340.000 (Released)</td>
                    <td class="px-4 py-3 text-right text-purple-600 font-medium">Rp 2.225.000 (Released)</td>
                    <td class="px-4 py-3 text-right text-sky-600 font-medium">Rp 1.335.000 (Released)</td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-600">Rp 0</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">FULLY SETTLED</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-slate-400 text-xs italic">Closed</span>
                    </td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">PKG-KOMODO-DIVE-4D</div>
                        <div class="text-[11px] text-slate-400">BKG-9311-C08</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Mr. Robert Taylor</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 24.000.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 12.000.000 (Locked)</td>
                    <td class="px-4 py-3 text-right text-purple-600 font-medium">Rp 8.000.000 (Locked)</td>
                    <td class="px-4 py-3 text-right text-sky-600 font-medium">Rp 4.000.000 (Locked)</td>
                    <td class="px-4 py-3 text-right font-bold text-amber-600">Rp 24.000.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">ESCROW LOCKED</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="px-2.5 py-1 text-[11px] bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 rounded font-medium transition">Release Check-in</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
