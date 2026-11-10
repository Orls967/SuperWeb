<x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700 pb-3">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center space-x-2">
            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Matriks Beban Kerja Antrean Aktif per Peran (Workload Matrix)</span>
        </h2>
        <span class="text-xs text-slate-400 font-normal">Real-Time Distribution</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="text-slate-400 border-b border-slate-700/60">
                    <th class="pb-2.5 font-semibold">Peran Operasional (Role)</th>
                    <th class="pb-2.5 font-semibold text-center text-amber-400">Urgent</th>
                    <th class="pb-2.5 font-semibold text-center text-rose-400">Tinggi (High)</th>
                    <th class="pb-2.5 font-semibold text-center text-blue-400">Normal</th>
                    <th class="pb-2.5 font-semibold text-center text-slate-400">Rendah (Low)</th>
                    <th class="pb-2.5 font-semibold text-right">Total Beban</th>
                    <th class="pb-2.5 font-semibold text-center">Status Kapasitas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <tr class="hover:bg-slate-800/40">
                    <td class="py-3 font-semibold text-slate-200">Finance &amp; Treasury Manager</td>
                    <td class="py-3 text-center font-bold text-amber-400">3</td>
                    <td class="py-3 text-center text-slate-300">5</td>
                    <td class="py-3 text-center text-slate-400">12</td>
                    <td class="py-3 text-center text-slate-500">4</td>
                    <td class="py-3 text-right font-bold text-white">24</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300">
                            Mendekati Kuota
                        </span>
                    </td>
                </tr>

                <tr class="hover:bg-slate-800/40">
                    <td class="py-3 font-semibold text-slate-200">Legal &amp; Contract Counsel</td>
                    <td class="py-3 text-center font-bold text-amber-400">1</td>
                    <td class="py-3 text-center text-slate-300">4</td>
                    <td class="py-3 text-center text-slate-400">8</td>
                    <td class="py-3 text-center text-slate-500">2</td>
                    <td class="py-3 text-right font-bold text-white">15</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
                            Normal Sehat
                        </span>
                    </td>
                </tr>

                <tr class="hover:bg-slate-800/40">
                    <td class="py-3 font-semibold text-slate-200">Workshop &amp; Fleet Supervisor</td>
                    <td class="py-3 text-center font-bold text-amber-400">4</td>
                    <td class="py-3 text-center text-slate-300">8</td>
                    <td class="py-3 text-center text-slate-400">16</td>
                    <td class="py-3 text-center text-slate-500">5</td>
                    <td class="py-3 text-right font-bold text-white">33</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300">
                            Beban Penuh
                        </span>
                    </td>
                </tr>

                <tr class="hover:bg-slate-800/40">
                    <td class="py-3 font-semibold text-slate-200">Procurement &amp; Vendor Auditor</td>
                    <td class="py-3 text-center font-bold text-amber-400">0</td>
                    <td class="py-3 text-center text-slate-300">3</td>
                    <td class="py-3 text-center text-slate-400">7</td>
                    <td class="py-3 text-center text-slate-500">6</td>
                    <td class="py-3 text-right font-bold text-white">16</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
                            Normal Sehat
                        </span>
                    </td>
                </tr>

                <tr class="hover:bg-slate-800/40">
                    <td class="py-3 font-semibold text-slate-200">Maritime &amp; Vessel Dispatcher</td>
                    <td class="py-3 text-center font-bold text-amber-400">2</td>
                    <td class="py-3 text-center text-slate-300">2</td>
                    <td class="py-3 text-center text-slate-400">5</td>
                    <td class="py-3 text-center text-slate-500">1</td>
                    <td class="py-3 text-right font-bold text-white">10</td>
                    <td class="py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
                            Tersedia Luang
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</x-ds.card>

