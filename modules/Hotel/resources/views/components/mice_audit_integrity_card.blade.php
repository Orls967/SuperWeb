@props([
    'auditKey',
    'isBalanced' => true,
    'totalBilled',
    'totalPaid'
])

<div class="p-4 bg-slate-900/80 border {{ $isBalanced ? 'border-emerald-500/40' : 'border-rose-500/40' }} rounded-xl space-y-2 font-mono text-xs">
    <div class="flex items-center justify-between font-sans">
        <span class="font-bold text-white">Integritas Rekonsiliasi Finansial</span>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $isBalanced ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
            {{ $isBalanced ? 'SEIMBANG & IDEMPOTEN' : 'SELISIH PEMBUKUAN' }}
        </span>
    </div>

    <div class="space-y-1 text-slate-300">
        <div class="flex justify-between">
            <span class="text-slate-400 font-sans">Total Tagihan SPK:</span>
            <span>IDR {{ number_format($totalBilled, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-slate-400 font-sans">Total Kas Diterima:</span>
            <span class="text-emerald-400">IDR {{ number_format($totalPaid, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="pt-2 border-t border-slate-800 text-[10px] text-slate-400 truncate">
        Idempotency-Key: <span class="text-slate-300">{{ $auditKey }}</span>
    </div>
</div>
