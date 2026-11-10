@props([
    'title',
    'value',
    'delta' => null,
    'deltaDirection' => 'neutral', // up, down, neutral
    'period' => null,
])

@php
$deltaColor = match($deltaDirection) {
    'up' => 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30',
    'down' => 'text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-950/30',
    default => 'text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800',
};

$deltaIcon = match($deltaDirection) {
    'up' => '↑',
    'down' => '↓',
    default => '•',
};
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm space-y-3']) }}>
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $title }}</span>
        @if(isset($icon))
            <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                {{ $icon }}
            </div>
        @endif
    </div>

    <div class="flex items-baseline justify-between gap-2">
        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 tabular-nums">
            {{ $value }}
        </span>

        @if($delta)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $deltaColor }}">
                <span>{{ $deltaIcon }}</span>
                <span>{{ $delta }}</span>
            </span>
        @endif
    </div>

    <div class="flex items-center justify-between pt-1">
        @if($period)
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $period }}</span>
        @else
            <span></span>
        @endif

        @if(isset($chart))
            <div class="h-8 w-28">
                {{ $chart }}
            </div>
        @endif
    </div>
</div>
