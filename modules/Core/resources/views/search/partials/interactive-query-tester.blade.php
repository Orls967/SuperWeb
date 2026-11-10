{{-- modules/Core/resources/views/search/partials/interactive-query-tester.blade.php --}}
<div x-data="{
        queryInput: 'kontrak pengadaan armada logistik',
        k1Param: 1.2,
        bParam: 0.75,
        avgDocLength: 45,
        targetDocLength: 52,
        sampleTerms: [
            { term: 'kontrak', tf: 3, idf: 2.15, isStopword: false },
            { term: 'pengadaan', tf: 2, idf: 1.84, isStopword: false },
            { term: 'armada', tf: 4, idf: 2.65, isStopword: false },
            { term: 'logistik', tf: 5, idf: 2.40, isStopword: false }
        ],
        calculateScore(term) {
            let tf = term.tf;
            let idf = term.idf;
            let lenNorm = 1 - this.bParam + (this.bParam * (this.targetDocLength / this.avgDocLength));
            let termScore = idf * ((tf * (this.k1Param + 1)) / (tf + (this.k1Param * lenNorm)));
            return termScore.toFixed(4);
        },
        calculateTotalScore() {
            let total = 0;
            for (let t of this.sampleTerms) {
                total += parseFloat(this.calculateScore(t));
            }
            return total.toFixed(4);
        }
    }"
    class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6"
    role="region"
    aria-label="Simulator Interaktif Relevansi BM25">

    <div class="flex items-start justify-between border-b border-gray-100 pb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900 tracking-tight flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Simulator & Penguji Relevansi BM25 Interaktif
            </h3>
            <p class="text-xs text-gray-500 mt-1">
                Eksperimen pembobotan term, normalisasi panjang dokumen, dan kalibrasi parameter k1 & b secara langsung.
            </p>
        </div>

        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
            A4: BM25 Okapi Algorithm
        </span>
    </div>

    {{-- Parameter Slider Controls --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-xl bg-gray-50 border border-gray-100">
        <div>
            <div class="flex justify-between items-center text-xs font-bold text-gray-700 mb-1">
                <label for="k1-slider">Saturasi Frekuensi (k1):</label>
                <span class="text-blue-600 font-mono" x-text="k1Param"></span>
            </div>
            <input type="range" id="k1-slider" min="0.5" max="3.0" step="0.1" x-model.number="k1Param"
                   class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
            <span class="text-[10px] text-gray-400">Standar: 1.2 – 2.0</span>
        </div>

        <div>
            <div class="flex justify-between items-center text-xs font-bold text-gray-700 mb-1">
                <label for="b-slider">Normalisasi Panjang (b):</label>
                <span class="text-blue-600 font-mono" x-text="bParam"></span>
            </div>
            <input type="range" id="b-slider" min="0.0" max="1.0" step="0.05" x-model.number="bParam"
                   class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
            <span class="text-[10px] text-gray-400">Standar: 0.75</span>
        </div>

        <div>
            <div class="flex justify-between items-center text-xs font-bold text-gray-700 mb-1">
                <label for="doclen-slider">Panjang Dokumen Uji:</label>
                <span class="text-blue-600 font-mono" x-text="targetDocLength + ' kata'"></span>
            </div>
            <input type="range" id="doclen-slider" min="10" max="200" step="5" x-model.number="targetDocLength"
                   class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
            <span class="text-[10px] text-gray-400">Rerata Korpus: 45 kata</span>
        </div>

        <div class="flex flex-col justify-center items-center p-3 rounded-lg bg-blue-600 text-white shadow-sm">
            <span class="text-[11px] font-medium opacity-90">Total Skor BM25 Dokumen</span>
            <span class="text-2xl font-black font-mono tracking-tight" x-text="calculateTotalScore()"></span>
        </div>
    </div>

    {{-- Breakdown Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/80 text-gray-600 font-bold uppercase tracking-wider">
                    <th class="p-3">Istilah Kata (Term)</th>
                    <th class="p-3">Frekuensi (TF)</th>
                    <th class="p-3">Inverse Doc Freq (IDF)</th>
                    <th class="p-3">Bobot Normalisasi</th>
                    <th class="p-3">Kontribusi Skor</th>
                    <th class="p-3">Porsi Relevansi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-mono">
                <template x-for="item in sampleTerms" :key="item.term">
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="p-3 font-semibold font-sans text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span x-text="item.term"></span>
                        </td>
                        <td class="p-3 text-gray-700" x-text="item.tf + 'x'"></td>
                        <td class="p-3 text-gray-700" x-text="item.idf.toFixed(2)"></td>
                        <td class="p-3 text-gray-600" x-text="(1 - bParam + (bParam * (targetDocLength / avgDocLength))).toFixed(3)"></td>
                        <td class="p-3 font-bold text-blue-700" x-text="calculateScore(item)"></td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-200 rounded-full h-2 overflow-hidden">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                         :style="'width: ' + Math.min(100, Math.round((parseFloat(calculateScore(item)) / parseFloat(calculateTotalScore())) * 100)) + '%'"></div>
                                </div>
                                <span class="text-[10px] text-gray-500 font-sans"
                                      x-text="Math.round((parseFloat(calculateScore(item)) / parseFloat(calculateTotalScore())) * 100) + '%'"></span>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Formula Footnote --}}
    <div class="rounded-xl p-4 bg-gray-900 text-gray-200 text-xs font-mono space-y-2">
        <div class="text-[11px] font-sans font-bold text-gray-400 uppercase tracking-wider">
            Rumus Pembobotan Okapi BM25:
        </div>
        <p class="text-blue-300">
            Score(D, Q) = &sum; IDF(q_i) &times; [ (TF(q_i, D) &times; (k_1 + 1)) / (TF(q_i, D) + k_1 &times; (1 - b + b &times; (|D| / avgdl))) ]
        </p>
        <p class="text-gray-400 text-[11px] font-sans">
            Algoritma ini diimplementasikan secara deterministik di <code class="text-white">Bm25Scorer.php</code> dan diverifikasi oleh <code class="text-white">Bm25CertificateVerifier.php</code>.
        </p>
    </div>
</div>

