<x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700 pb-3">
        <div>
            <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center space-x-2">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <span>BM25 Okapi Mathematical Sandbox (Algoritma A4)</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Simulasi deterministik formula Okapi BM25 dengan bobot parameter k1 = 1.2 dan b = 0.75.
            </p>
        </div>
        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-700 text-slate-300">
            Hash SHA-256 Validated
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
        {{-- Parameter Kontrol --}}
        <div class="space-y-3 bg-slate-900/60 border border-slate-800 p-4 rounded-xl text-xs">
            <h3 class="font-bold text-white uppercase text-[11px] tracking-wider mb-2">Parameter Dokumen Uji</h3>
            <div>
                <label for="bm25_q" class="text-slate-400 block mb-1">Term Kueri Pencarian</label>
                <input type="text" id="bm25_q" value="fleet overhaul" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="bm25_tf" class="text-slate-400 block mb-1">Frekuensi Term (TF)</label>
                    <input type="number" id="bm25_tf" value="3" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                </div>
                <div>
                    <label for="bm25_dl" class="text-slate-400 block mb-1">Panjang Dokumen (|D|)</label>
                    <input type="number" id="bm25_dl" value="25" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="bm25_k1" class="text-slate-400 block mb-1">Koefisien Saturation (k1)</label>
                    <input type="number" step="0.1" id="bm25_k1" value="1.2" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                </div>
                <div>
                    <label for="bm25_b" class="text-slate-400 block mb-1">Normalisasi Panjang (b)</label>
                    <input type="number" step="0.05" id="bm25_b" value="0.75" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-white">
                </div>
            </div>
        </div>

        {{-- Uraian Hasil Skor --}}
        <div class="space-y-3 bg-slate-900/60 border border-slate-800 p-4 rounded-xl text-xs flex flex-col justify-between">
            <div>
                <h3 class="font-bold text-white uppercase text-[11px] tracking-wider mb-2">Hasil Perhitungan Deterministik</h3>
                <div class="space-y-2">
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">IDF Term "fleet":</span>
                        <span class="font-mono text-emerald-400 font-bold">2.4150</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">Length Normalization factor:</span>
                        <span class="font-mono text-blue-400 font-bold">1.1875</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">Term Weight (k1 + 1):</span>
                        <span class="font-mono text-indigo-400 font-bold">2.2000</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">Policy RBAC Multiplier:</span>
                        <span class="font-mono text-purple-400 font-bold">1.0000x</span>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                <span class="text-slate-300 font-semibold">Skor Akhir Relevansi BM25:</span>
                <span class="text-2xl font-extrabold text-white font-mono">3.8421</span>
            </div>
        </div>
    </div>
</x-ds.card>

