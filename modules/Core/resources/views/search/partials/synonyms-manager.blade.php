<x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700 pb-3">
        <div>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center space-x-2">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span>Thesaurus Sinonim &amp; Perluasan Kueri Otomatis (Thesaurus Synonyms)</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Kamus perluasan otomatis yang memetakan istilah non-teknis ke token dokumen teknis.
            </p>
        </div>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-700 text-slate-300">
            Bidirectional Map
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">mobil &harr; armada</span>
            <p class="text-[11px] text-slate-400">Padanan: kendaraan, unit, vehicle, fleet, car</p>
            <span class="inline-block mt-1 text-[10px] text-blue-400 font-mono">Modul: AutoServe, Fleet</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">faktur &harr; invoice</span>
            <p class="text-[11px] text-slate-400">Padanan: tagihan, nota, bill, receipt, voucher</p>
            <span class="inline-block mt-1 text-[10px] text-emerald-400 font-mono">Modul: Finance, Trade</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">kontrak &harr; agreement</span>
            <p class="text-[11px] text-slate-400">Padanan: perjanjian, mou, pks, legal covenant</p>
            <span class="inline-block mt-1 text-[10px] text-indigo-400 font-mono">Modul: Contract, Partner</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">kapal &harr; vessel</span>
            <p class="text-[11px] text-slate-400">Padanan: tongkang, tugboat, boat, pelayaran</p>
            <span class="inline-block mt-1 text-[10px] text-cyan-400 font-mono">Modul: Marine, Logistics</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">kamar &harr; suite</span>
            <p class="text-[11px] text-slate-400">Padanan: room, villa, resort, banquet, venue</p>
            <span class="inline-block mt-1 text-[10px] text-amber-400 font-mono">Modul: Hotel, Hospital</span>
        </div>

        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-3.5 space-y-1">
            <span class="font-bold text-white block">karyawan &harr; pegawai</span>
            <p class="text-[11px] text-slate-400">Padanan: staff, employee, pekerja, teknisi</p>
            <span class="inline-block mt-1 text-[10px] text-purple-400 font-mono">Modul: Hcm, Core</span>
        </div>
    </div>
</x-ds.card>

