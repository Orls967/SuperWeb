@props(['contract' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Struktur Perjanjian Tarif Khusus Korporat (Negotiated Corporate Rate)
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Ketentuan tarif kamar terdiskon tahunan, fasilitas inklusi rapat, sarapan, serta penjaminan direct billing.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 rounded-full">
            Kontrak B2B No. CTR-2026-ASTRA
        </span>
    </div>

    <!-- Rate Breakdown Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Kategori Tipe Kamar</th>
                    <th class="px-4 py-3 text-right">Tarif Publik (BAR)</th>
                    <th class="px-4 py-3 text-right text-indigo-600 font-bold">Tarif Kontrak Korporat</th>
                    <th class="px-4 py-3 text-center">Fasilitas Inklusi</th>
                    <th class="px-4 py-3 text-center">Klausul Blackout Date</th>
                    <th class="px-4 py-3 text-right">Plafon Kuota / Malam</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">Deluxe Business Room</td>
                    <td class="px-4 py-3 text-right line-through text-slate-400">Rp 1.850.000</td>
                    <td class="px-4 py-3 text-right font-bold text-indigo-600">Rp 1.250.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Sarapan 2 Pax + Laundry 2 Pcs</span>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-500">Kecuali H-3 sd H+3 Lebaran</td>
                    <td class="px-4 py-3 text-right font-medium">Maksimal 15 Kamar</td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">Executive Ocean Suite</td>
                    <td class="px-4 py-3 text-right line-through text-slate-400">Rp 3.500.000</td>
                    <td class="px-4 py-3 text-right font-bold text-indigo-600">Rp 2.450.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-800">Akses Club Lounge + Airport Transfer</span>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-500">Kecuali 24 Des - 2 Jan</td>
                    <td class="px-4 py-3 text-right font-medium">Maksimal 5 Kamar</td>
                </tr>

                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                    <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-100">Presidential 2-Bedroom Villa</td>
                    <td class="px-4 py-3 text-right line-through text-slate-400">Rp 8.500.000</td>
                    <td class="px-4 py-3 text-right font-bold text-indigo-600">Rp 6.200.000</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800">Butler 24 Jam + In-Villa BBQ</span>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-500">Otorisasi Tertulis GM</td>
                    <td class="px-4 py-3 text-right font-medium">Maksimal 1 Kamar</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Contract Terms Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg">
            <span class="text-slate-400 font-medium">Termin Penagihan Faktur (Payment Terms):</span>
            <span class="font-bold text-slate-800 dark:text-slate-200 block mt-1">Net 30 Hari Kalender</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg">
            <span class="text-slate-400 font-medium">Batas Pembatalan Bebas Penalti:</span>
            <span class="font-bold text-slate-800 dark:text-slate-200 block mt-1">Maksimal 24 Jam Sebelum Check-In</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-lg">
            <span class="text-slate-400 font-medium">Penalti Keterlambatan Pembayaran:</span>
            <span class="font-bold text-rose-600 dark:text-rose-400 block mt-1">1.5% Per Bulan (Compound)</span>
        </div>
    </div>
</div>
