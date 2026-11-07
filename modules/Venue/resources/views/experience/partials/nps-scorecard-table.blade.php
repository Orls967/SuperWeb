<div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4" x-data="npsScorecardTableComponent()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="p-1 rounded-md bg-purple-500/20 text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </span>
                Rincian Tanggapan Ulasan & NPS Pasca Acara (Brand Scorecard)
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Korelasi rating tamu dengan waktu tunggu layanan bar dan kenyamanan desakan massa.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-emerald-400 font-bold">Promoter: 74%</span> •
            <span class="text-slate-400">Pasif: 20%</span> •
            <span class="text-rose-400 font-bold">Detractor: 6%</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-900 text-slate-400 font-mono uppercase text-[11px] border-b border-slate-800">
                <tr>
                    <th class="py-2.5 px-3">Tamu</th>
                    <th class="py-2.5 px-3">Skor NPS</th>
                    <th class="py-2.5 px-3">Kategori</th>
                    <th class="py-2.5 px-3">Komentar Tamu</th>
                    <th class="py-2.5 px-3">Waktu Pelayanan (WPS)</th>
                    <th class="py-2.5 px-3 text-right">Klasifikasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80 font-sans text-[12px]">
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 font-medium text-white">Reza Kurniawan</td>
                    <td class="py-3 px-3 font-mono font-black text-emerald-400 text-sm">10 / 10</td>
                    <td class="py-3 px-3 text-slate-300">Akses Pintu Face-ID</td>
                    <td class="py-3 px-3 text-slate-300">Masuk gerbang sangat cepat tanpa perlu keluarkan tiket fisik, mantap!</td>
                    <td class="py-3 px-3 font-mono text-emerald-400">12 detik</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-emerald-300">PROMOTER</span>
                    </td>
                </tr>
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 font-medium text-white">Jessica Tan</td>
                    <td class="py-3 px-3 font-mono font-black text-amber-400 text-sm">8 / 10</td>
                    <td class="py-3 px-3 text-slate-300">Layanan Bar VIP</td>
                    <td class="py-3 px-3 text-slate-300">Minuman enak dan promo voucher 20% langsung muncul di app.</td>
                    <td class="py-3 px-3 font-mono text-amber-400">45 detik</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300">PASIF</span>
                    </td>
                </tr>
                <tr class="hover:bg-slate-900/50">
                    <td class="py-3 px-3 font-medium text-white">Budi Santoso</td>
                    <td class="py-3 px-3 font-mono font-black text-rose-400 text-sm">6 / 10</td>
                    <td class="py-3 px-3 text-slate-300">Antrean Bar Utara</td>
                    <td class="py-3 px-3 text-slate-300">Antrean minuman di dekat panggung utama agak padat saat puncak acara.</td>
                    <td class="py-3 px-3 font-mono text-rose-400">85 detik</td>
                    <td class="py-3 px-3 text-right">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/20 text-rose-300">DETRACTOR</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function npsScorecardTableComponent() {
    return {
        filterCategory: 'ALL'
    }
}
</script>
