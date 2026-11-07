@props(['unit' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6" x-data="{
    grossIncome: 45000000,
    maintenanceFeeBps: 1500,
    totalShares: 100,
    investorShares: 60,
    get managementFee() {
        return Math.round((this.grossIncome * this.maintenanceFeeBps) / 10000);
    },
    get distributablePool() {
        return Math.max(0, this.grossIncome - this.managementFee);
    },
    get investorDividend() {
        return Math.round((this.distributablePool * this.investorShares) / this.totalShares);
    }
}">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                </svg>
                Kalkulator Interaktif Imbal Hasil Rental Pool Timeshare
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Simulasi pembagian dividen bersih dari pendapatan sewa kamar vila ke pemegang token fraksional.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 rounded-full">
            Algoritma H8 Bersertifikat
        </span>
    </div>

    <!-- Interactive Inputs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Pendapatan Kotor Bulanan (IDR)</label>
            <input type="number" x-model.number="grossIncome" class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100">
            <p class="text-[11px] text-slate-400">Total sewa kamar yang masuk pool dalam 30 hari.</p>
        </div>

        <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Biaya Pengelolaan Resort (bps)</label>
            <input type="number" x-model.number="maintenanceFeeBps" class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100">
            <p class="text-[11px] text-slate-400">1500 bps = 15.00% biaya operasional & sinking fund.</p>
        </div>

        <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Porsi Saham Investor (Token Shares)</label>
            <input type="number" x-model.number="investorShares" class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100">
            <p class="text-[11px] text-slate-400">Dari total 100 share kepemilikan vila.</p>
        </div>
    </div>

    <!-- Output Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-500 uppercase">Gross Rental Pool</div>
            <div class="text-lg font-bold text-slate-800 dark:text-slate-100 mt-1" x-text="'Rp ' + grossIncome.toLocaleString('id-ID')"></div>
        </div>

        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60">
            <div class="text-[11px] font-medium text-rose-600 uppercase">Resort Maintenance Cut</div>
            <div class="text-lg font-bold text-rose-700 dark:text-rose-400 mt-1" x-text="'- Rp ' + managementFee.toLocaleString('id-ID')"></div>
        </div>

        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900/60">
            <div class="text-[11px] font-medium text-blue-600 uppercase">Distributable Net Pool</div>
            <div class="text-lg font-bold text-blue-700 dark:text-blue-400 mt-1" x-text="'Rp ' + distributablePool.toLocaleString('id-ID')"></div>
        </div>

        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60">
            <div class="text-[11px] font-medium text-emerald-600 uppercase">Hak Dividen Investor</div>
            <div class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mt-1" x-text="'Rp ' + investorDividend.toLocaleString('id-ID')"></div>
        </div>
    </div>
</div>
