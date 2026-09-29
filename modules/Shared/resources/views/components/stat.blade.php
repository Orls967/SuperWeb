@props([
    'title',
    'value',
    'subtext' => null,
    'icon' => null,
    'color' => 'blue', // blue, emerald, amber, purple, rose
])

@php
$colorClasses = [
    'blue' => 'from-blue-500/20 to-blue-600/10 text-blue-400 border-blue-500/30',
    'emerald' => 'from-emerald-500/20 to-emerald-600/10 text-emerald-400 border-emerald-500/30',
    'amber' => 'from-amber-500/20 to-amber-600/10 text-amber-400 border-amber-500/30',
    'purple' => 'from-purple-500/20 to-purple-600/10 text-purple-400 border-purple-500/30',
    'rose' => 'from-rose-500/20 to-rose-600/10 text-rose-400 border-rose-500/30',
][$color] ?? 'from-blue-500/20 to-blue-600/10 text-blue-400 border-blue-500/30';
@endphp

<div {{ $attributes->merge(['class' => 'bg-slate-800/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl p-5 shadow-xl relative overflow-hidden flex items-start justify-between']) }}>
    <div>
        <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ $title }}</p>
        <p class="text-2xl font-bold text-slate-100 mt-1 tracking-tight">{{ $value }}</p>
        @if($subtext)
            <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">{{ $subtext }}</p>
        @endif
    </div>

    @if($icon || isset($slot) && $slot->isNotEmpty())
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br {{ $colorClasses }} border flex items-center justify-center flex-shrink-0 shadow-lg">
            {{ $icon ?? $slot }}
        </div>
    @endif
</div>
