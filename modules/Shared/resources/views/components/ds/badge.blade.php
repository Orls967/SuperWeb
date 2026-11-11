@props([
    'variant' => 'neutral',
    'size' => 'md',
])

@php
$sizeClasses = match($size) {
    'sm' => 'px-2 py-0.5 text-[11px]',
    'lg' => 'px-3 py-1 text-sm',
    default => 'px-2.5 py-0.5 text-xs',
};

$variantClasses = match($variant) {
    'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50',
    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50',
    'danger' => 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300 border border-red-200 dark:border-red-800/50',
    'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50',
    default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full {$sizeClasses} {$variantClasses} select-none"]) }}>
    {{ $slot }}
</span>

