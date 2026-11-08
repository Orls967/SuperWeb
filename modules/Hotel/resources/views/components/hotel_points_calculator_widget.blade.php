@props([
    'defaultSpend' => 5000000,
    'defaultLine' => 'HTL_ROOM',
])

<div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl p-6 border border-indigo-800/40 shadow-xl"
     x-data="{
        spendIdr: {{ $defaultSpend }},
        selectedLine: '{{ $defaultLine }}',
        memberTier: 'SILVER',
        lineWeights: {
            'HTL_ROOM': 1.0,
            'HTL_FNB': 1.2,
            'VEN_TICKET': 1.5,
            'VEN_VIP': 1.75,
            'RST_DINE': 1.1,
            'SPA_TREAT': 1.3,
            'MED_CHECK': 1.25,
            'MOB_TRANS': 1.0,
            'ESG_DONAT': 2.0
        },
        tierMultipliers: {
            'SILVER': 1.0,
            'GOLD': 1.25,
            'PLATINUM': 1.5,
            'BLACK': 2.0
        },
        get calculatedPoints() {
            let weight = this.lineWeights[this.selectedLine] || 1.0;
            let mult = this.tierMultipliers[this.memberTier] || 1.0;
            return Math.floor((this.spendIdr / 1000) * weight * mult);
        },
        get pointCashValue() {
            return this.calculatedPoints * 100;
        }
     }">
    <div class="flex items-center justify-between pb-4 border-b border-indigo-800/40 mb-5">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-amber-400/20 text-amber-300 flex items-center justify-center font-bold text-lg">
                🧮
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Kalkulator Estimasi Perolehan Poin (Points Accrual)</h3>
                <p class="text-xs text-indigo-300">Simulasi Perolehan Poin di 17 Lini Ekosistem Berdasarkan Nilai Belanja</p>
            </div>
        </div>
        <span class="px-2.5 py-1 bg-indigo-500/20 text-indigo-300 text-xs font-bold rounded-lg border border-indigo-400/30">
            Formula Transparan
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <div class="space-y-4 text-xs">
            <div>
                <div class="flex justify-between items-center text-slate-300 font-semibold mb-1.5">
                    <span>Estimasi Nilai Belanja Kotor (IDR):</span>
                    <span class="text-amber-400 font-bold font-mono text-sm" x-text="'Rp ' + spendIdr.toLocaleString()"></span>
                </div>
                <input type="range" x-model.number="spendIdr" min="100000" max="50000000" step="500000"
                       class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-indigo-500">
            </div>

            <div>
                <label class="block font-semibold text-slate-300 mb-1.5">Pilih Kategori Lini Ekosistem:</label>
                <select x-model="selectedLine" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white font-semibold">
                    <option value="HTL_ROOM">Kamar Hotel & Resor (Bobot: 1.0x)</option>
                    <option value="HTL_FNB">Makanan & Minuman Inap (Bobot: 1.2x)</option>
                    <option value="VEN_TICKET">Tiket Festival & Konser Venue (Bobot: 1.5x)</option>
                    <option value="VEN_VIP">Meja VIP Club & Lounge (Bobot: 1.75x)</option>
                    <option value="SPA_TREAT">Perawatan Spa & Wellness (Bobot: 1.3x)</option>
                    <option value="MED_CHECK">Pemeriksaan Wisata Medis (Bobot: 1.25x)</option>
                    <option value="ESG_DONAT">Donasi Konservasi Terumbu Karang (Bobot: 2.0x)</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-300 mb-1.5">Tingkat Keanggotaan Anda:</label>
                <div class="grid grid-cols-4 gap-2 text-center font-bold">
                    <button type="button" @click="memberTier = 'SILVER'"
                            :class="memberTier === 'SILVER' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400'"
                            class="py-1.5 rounded-lg border border-slate-700 transition">
                        Silver (1.0x)
                    </button>
                    <button type="button" @click="memberTier = 'GOLD'"
                            :class="memberTier === 'GOLD' ? 'bg-amber-600 text-white' : 'bg-slate-800 text-slate-400'"
                            class="py-1.5 rounded-lg border border-slate-700 transition">
                        Gold (1.25x)
                    </button>
                    <button type="button" @click="memberTier = 'PLATINUM'"
                            :class="memberTier === 'PLATINUM' ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-400'"
                            class="py-1.5 rounded-lg border border-slate-700 transition">
                        Plat (1.5x)
                    </button>
                    <button type="button" @click="memberTier = 'BLACK'"
                            :class="memberTier === 'BLACK' ? 'bg-zinc-950 text-amber-300 border-amber-500/50' : 'bg-slate-800 text-slate-400'"
                            class="py-1.5 rounded-lg border border-slate-700 transition">
                        Black (2.0x)
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-slate-950/60 rounded-xl p-5 border border-indigo-900/60 flex flex-col items-center justify-center text-center space-y-2">
            <span class="text-xs uppercase tracking-wider text-indigo-300 font-bold">Estimasi Poin yang Anda Dapatkan</span>
            <div class="text-4xl font-black text-amber-300" x-text="calculatedPoints.toLocaleString() + ' PTS'"></div>
            <div class="text-xs text-slate-300">
                Nilai Tukar Tunai Setara: <strong class="text-emerald-400" x-text="'Rp ' + pointCashValue.toLocaleString()"></strong>
            </div>
            <p class="text-[10px] text-slate-500 pt-2 border-t border-slate-800 max-w-xs">
                Poin otomatis dikreditkan ke saldo Travel Pass Anda saat checkout atau transaksi selesai.
            </p>
        </div>
    </div>
</div>

