@props([
    'marginPercent' => 0.0,
    'minThreshold' => 40.0
])

@php
    $isPassed = $marginPercent >= $minThreshold;
@endphp

<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold font-mono border {{ $isPassed ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-rose-500/10 text-rose-400 border-rose-500/30' }}">
    @if($isPassed)
        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span>GM {{ number_format($marginPercent, 1) }}% &ge; {{ number_format($minThreshold, 1) }}%</span>
    @else
        <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <span>GM {{ number_format($marginPercent, 1) }}% &lt; {{ number_format($minThreshold, 1) }}% (PELANGGARAN)</span>
    @endif
</div>
