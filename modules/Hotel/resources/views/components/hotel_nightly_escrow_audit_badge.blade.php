@props(['audit' => null])

<div class="bg-white dark:bg-slate-900 shadow-sm rounded-xl border border-slate-200 dark:border-slate-800 p-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="p-2 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div>
            <div class="text-xs font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>Status Audit Kliring Escrow: RECONCILED</span>
                <span class="px-2 py-0.5 text-[10px] font-mono bg-emerald-100 text-emerald-800 rounded">100% BALANCED</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Seluruh saldo titipan dana paket multi-vendor cocok sempurna dengan saldo buku besar perbankan.</p>
        </div>
    </div>
    <div class="flex items-center gap-2 text-xs">
        <span class="font-mono text-slate-500">Checkpoint: {{ date('Y-m-d H:i') }} WITA</span>
        <button class="px-2.5 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded transition">
            Lihat Log
        </button>
    </div>
</div>
