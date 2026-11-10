<div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <h3 class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center space-x-2">
            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Ringkasan Integritas Indeks &amp; Kueri</span>
        </h3>
        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
            SLA HIJAU
        </span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div>
            <span class="text-slate-400 block">Algoritma Penilai:</span>
            <span class="text-white font-semibold font-mono mt-0.5 block">Okapi BM25</span>
        </div>
        <div>
            <span class="text-slate-400 block">Toleransi Saltik:</span>
            <span class="text-white font-semibold font-mono mt-0.5 block">Trigram Jaccard</span>
        </div>
        <div>
            <span class="text-slate-400 block">Model Sekuritas:</span>
            <span class="text-emerald-400 font-semibold font-mono mt-0.5 block">Policy-First Zero-Leak</span>
        </div>
        <div>
            <span class="text-slate-400 block">State Machine:</span>
            <span class="text-indigo-400 font-semibold font-mono mt-0.5 block">8 Status Idempoten</span>
        </div>
    </div>
</div>

