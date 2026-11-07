@props(['accounts' => []])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Matriks Aging AR Piutang City Ledger Korporat
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Klasifikasi portofolio piutang berdasarkan bucket jatuh tempo (0-30 hari, 31-60 hari, 61-90 hari, dan 90+ hari).</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                100% Audit Reconciled
            </span>
            <button type="button" class="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition">
                Export Aging PDF / XLSX
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Nama Korporat / NPWP</th>
                    <th class="px-4 py-3">Plafon Kredit</th>
                    <th class="px-4 py-3 text-right">Lancar (0-30 Hari)</th>
                    <th class="px-4 py-3 text-right">Perhatian (31-60 Hari)</th>
                    <th class="px-4 py-3 text-right">Kurang Lancar (61-90 Hari)</th>
                    <th class="px-4 py-3 text-right text-rose-600">Macet (>90 Hari)</th>
                    <th class="px-4 py-3 text-right font-bold">Total Terutang</th>
                    <th class="px-4 py-3 text-center">Status Portofolio</th>
                    <th class="px-4 py-3 text-center">Aksi Tagih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">PT Astra International Tbk</div>
                        <div class="text-[11px] text-slate-400">NPWP: 01.000.013.1-092.000</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Rp 150.000.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 38.500.000</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 38.500.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">PRIME PASS</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="px-2 py-1 text-[11px] bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 rounded font-medium transition">Kirim Statement</button>
                    </td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">PT Telkom Indonesia (Persero) Tbk</div>
                        <div class="text-[11px] text-slate-400">NPWP: 01.000.015.6-093.000</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Rp 250.000.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 45.000.000</td>
                    <td class="px-4 py-3 text-right text-amber-600 font-medium">Rp 39.200.000</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 84.200.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">WATCHLIST 60D</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="px-2 py-1 text-[11px] bg-amber-50 hover:bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 rounded font-medium transition">Eskalasi Somasi</button>
                    </td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800 dark:text-slate-100">GoTo Gojek Tokopedia</div>
                        <div class="text-[11px] text-slate-400">NPWP: 02.444.888.1-011.000</div>
                    </td>
                    <td class="px-4 py-3 font-medium">Rp 100.000.000</td>
                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">Rp 12.500.000</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right text-slate-400">Rp 0</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800 dark:text-slate-100">Rp 12.500.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">HEALTHY</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="px-2 py-1 text-[11px] bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 rounded font-medium transition">Kirim Statement</button>
                    </td>
                </tr>
            </tbody>
            <tfoot class="bg-slate-100/70 dark:bg-slate-800/80 font-bold text-slate-800 dark:text-slate-200">
                <tr>
                    <td class="px-4 py-3" colspan="2">TOTAL KONSOLIDASI CITY LEDGER AR</td>
                    <td class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400">Rp 96.000.000</td>
                    <td class="px-4 py-3 text-right text-amber-700 dark:text-amber-400">Rp 39.200.000</td>
                    <td class="px-4 py-3 text-right text-rose-600">Rp 0</td>
                    <td class="px-4 py-3 text-right text-rose-700">Rp 0</td>
                    <td class="px-4 py-3 text-right text-indigo-700 dark:text-indigo-400 text-sm">Rp 135.200.000</td>
                    <td class="px-4 py-3 text-center text-xs font-normal text-slate-500" colspan="2">Utilisasi Plafon: 27.04%</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
