@props([
    'totalLiabilityIdr' => 250000000,
    'totalBreakageIdr' => 45000000,
    'totalTransferFeeIdr' => 12500000,
    'totalPartnerPayableIdr' => 78000000,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold">
                🏛️
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Rekapitulasi Akun Buku Besar Loyalitas</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Sinkronisasi Saldo Double-Entry (PROGRESS R0.10 & KONSEP §A14.5)</p>
            </div>
        </div>
        <span class="px-3 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-bold rounded-full border border-emerald-200 dark:border-emerald-800 flex items-center space-x-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Buku Besar Seimbang (Zero Discrepancy)</span>
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
            <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">htl:loyalty_points_liability:IDR</div>
            <div class="text-lg font-black text-rose-600 dark:text-rose-400">
                Rp {{ number_format($totalLiabilityIdr, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Liabilitas Poin Aktif Beredar</div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
            <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">htl:loyalty_breakage_revenue:IDR</div>
            <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                Rp {{ number_format($totalBreakageIdr, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Pendapatan Poin Hangus IFRS 15</div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
            <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">htl:transfer_fee_revenue:IDR</div>
            <div class="text-lg font-black text-indigo-600 dark:text-indigo-400">
                Rp {{ number_format($totalTransferFeeIdr, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Komisi Platform Transfer Aliansi</div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
            <div class="text-slate-400 text-[10px] uppercase font-bold tracking-wider mb-1">htl:partner_points_payable:IDR</div>
            <div class="text-lg font-black text-amber-600 dark:text-amber-400">
                Rp {{ number_format($totalPartnerPayableIdr, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Utang Settlement Maskapai Mitra</div>
        </div>
    </div>
</div>

