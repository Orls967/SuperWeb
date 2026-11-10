@props([
    'title' => null,
    'subtitle' => null,
    'variant' => 'default', // default, bordered, elevated
])

@php
$cardClasses = 'rounded-xl bg-white dark:bg-slate-800 transition-shadow';
$cardClasses .= match($variant) {
    'bordered' => ' border-2 border-slate-300 dark:border-slate-600 shadow-none',
    'elevated' => ' border border-slate-100 dark:border-slate-700 shadow-md',
    default => ' border border-slate-200 dark:border-slate-700 shadow-sm',
};
@endphp

<div {{ $attributes->merge(['class' => $cardClasses]) }}>
    @if($title || isset($header))
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
            <div>
                @if($title)
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($header))
                <div>{{ $header }}</div>
            @endif
        </div>
    @endif

    <div class="p-5">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-5 py-3 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700/80 rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>
