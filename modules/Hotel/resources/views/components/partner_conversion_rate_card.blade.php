@props([
    'partnerCode' => 'GARUDA_AIR',
    'partnerName' => 'Garuda Indonesia Airlines',
    'category' => 'AIRLINE',
    'exchangeRatio' => 10.0,
    'feePercentage' => 0.035,
    'ptsBalance' => 50000,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
     x-data="{
        pointsInput: 10000,
        exchangeRatio: {{ $exchangeRatio }},
        feePercent: {{ $feePercentage }},
        get calculatedUnits() {
            return Math.floor(this.pointsInput / this.exchangeRatio);
        },
        get feeIdr() {
            return Math.ceil((this.pointsInput * 100) * this.feePercent);
        },
        get netPayableIdr() {
            return (this.pointsInput * 100) - this.feeIdr;
        }
     }">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 mb-5">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center font-black">
                @if($category === 'AIRLINE') ✈️ @elseif($category === 'RAILWAY') 🚆 @else 🚗 @endif
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $partnerName }}</h3>
                <span class="text-xs text-slate-400">Kode Aliansi: <code class="text-sky-600">{{ $partnerCode }}</code></span>
            </div>
        </div>
        <span class="px-2.5 py-1 bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 text-xs font-bold rounded-lg border border-sky-200 dark:border-sky-800">
            Kurs: {{ $exchangeRatio }} PTS = 1 Unit
        </span>
    </div>

    <div class="space-y-4">
        <div>
            <div class="flex justify-between items-center text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                <span>Poin Hotel yang Ditransfer (PTS)</span>
                <span class="text-indigo-600 dark:text-indigo-400">Saldo: {{ number_format($ptsBalance) }} PTS</span>
            </div>
            <input type="number" x-model.number="pointsInput" min="1000" max="{{ $ptsBalance }}" step="1000"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-semibold focus:ring-2 focus:ring-sky-500">
        </div>

        <!-- Simulation Summary Box -->
        <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-4 border border-slate-200/60 dark:border-slate-800 space-y-2.5 text-xs">
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Unit Mitra Diterima (Miles/Voucher):</span>
                <span class="font-bold text-slate-900 dark:text-white text-sm" x-text="calculatedUnits.toLocaleString() + ' Unit'"></span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Biaya Platform ({{ $feePercentage * 100 }}% Admin Fee):</span>
                <span class="font-semibold text-rose-500" x-text="'Rp ' + feeIdr.toLocaleString()"></span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700 pt-2 font-bold">
                <span>Net Penyelesaian ke Mitra:</span>
                <span class="text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + netPayableIdr.toLocaleString()"></span>
            </div>
        </div>

        <button type="button" class="w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl shadow-md transition text-xs flex items-center justify-center space-x-2">
            <span>Kirim Instruksi Transfer Aliansi</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </button>
    </div>
</div>

