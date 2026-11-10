@props([
    'title' => null,
    'label' => null,
    'value' => 0,
    'delta' => null,
    'deltaDirection' => 'neutral', // up, down, neutral
    'change' => null,
    'changeType' => null,
    'period' => null,
    'hint' => null,
    'icon' => null,
])

@php
$statTitle = $title ?? $label ?? 'Statistik';
$statDelta = $delta ?? $change;
$statDirection = match($changeType) {
    'positive' => 'up',
    'negative' => 'down',
    default => $deltaDirection,
};
$statPeriod = $period ?? $hint;

$deltaColor = match($statDirection) {
    'up' => 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30',
    'down' => 'text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-950/30',
    default => 'text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800',
};

$deltaIcon = match($statDirection) {
    'up' => '↑',
    'down' => '↓',
    default => '•',
};
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm space-y-3']) }}>
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $statTitle }}</span>
        @if($icon)
            <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                @if($icon === 'cube')
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                @elseif($icon === 'check-circle')
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @elseif($icon === 'chart-bar')
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                @else
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
            </div>
        @endif
    </div>

    <div class="flex items-baseline justify-between gap-2">
        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 tabular-nums">
            {{ $value }}
        </span>

        @if($statDelta)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $deltaColor }}">
                <span>{{ $deltaIcon }}</span>
                <span>{{ $statDelta }}</span>
            </span>
        @endif
    </div>

    <div class="flex items-center justify-between pt-1">
        @if($statPeriod)
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $statPeriod }}</span>
        @else
            <span></span>
        @endif
    </div>
</div>
