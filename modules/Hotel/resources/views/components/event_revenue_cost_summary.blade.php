@props([
    'totalRevenue',
    'totalCost',
    'grossMarginPercent',
    'status' => 'APPROVED'
])

@php
    $grossMarginIdr = $totalRevenue - $totalCost;
    $isApproved = ($status === 'APPROVED' || $grossMarginPercent >= 40.0);
@endphp

<div class="bg-gradient-to-br from-slate-900 to-slate-850 p-5 rounded-2xl border border-slate-700 space-y-4 font-sans">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
        <h4 class="text-sm font-bold text-white uppercase tracking-wider">Ringkasan Finansial Event</h4>
        <span class="px-2.5 py-1 text-xs font-bold rounded {{ $isApproved ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
            {{ $isApproved ? 'GUARDRAIL LOLOS' : 'GUARDRAIL DITOLAK' }}
        </span>
    </div>

    <div class="space-y-2 text-xs font-mono">
        <div class="flex justify-between py-1 border-b border-slate-800/80">
            <span class="text-slate-400 font-sans">Total Nilai Pendapatan:</span>
            <span class="text-white font-bold">IDR {{ number_format($totalRevenue, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between py-1 border-b border-slate-800/80">
            <span class="text-slate-400 font-sans">Total Biaya Pokok Langsung:</span>
            <span class="text-rose-400 font-bold">IDR {{ number_format($totalCost, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between py-1 border-b border-slate-800/80">
            <span class="text-slate-400 font-sans">Margin Kotor Moneter:</span>
            <span class="text-emerald-400 font-bold">IDR {{ number_format($grossMarginIdr, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between pt-1 text-sm font-bold">
            <span class="text-slate-200 font-sans">Margin Kotor Persentase:</span>
            <span class="{{ $isApproved ? 'text-emerald-400' : 'text-rose-400' }}">{{ number_format($grossMarginPercent, 2) }}%</span>
        </div>
    </div>
</div>
