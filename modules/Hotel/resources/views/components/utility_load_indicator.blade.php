@props([
    'label',
    'currentLoad',
    'maxLoad',
    'unit' => 'kVA'
])

@php
    $ratio = $maxLoad > 0 ? min(100, round(($currentLoad / $maxLoad) * 100, 1)) : 0;
    $isWarning = $ratio >= 80.0;
@endphp

<div class="space-y-1.5 p-3 bg-slate-900/60 rounded-lg border border-slate-700/60 font-sans">
    <div class="flex justify-between text-xs text-slate-300">
        <span>{{ $label }}:</span>
        <strong class="font-mono {{ $isWarning ? 'text-amber-400' : 'text-emerald-400' }}">
            {{ $currentLoad }} / {{ $maxLoad }} {{ $unit }} ({{ $ratio }}%)
        </strong>
    </div>
    <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
        <div class="{{ $isWarning ? 'bg-amber-500' : 'bg-emerald-500' }} h-full rounded-full transition-all duration-300" style="width: {{ $ratio }}%"></div>
    </div>
    @if($isWarning)
        <div class="text-[10px] text-amber-400 flex items-center gap-1">
            <span>&warning; Beban mendekati batas aman 80% rating breaker</span>
        </div>
    @endif
</div>
