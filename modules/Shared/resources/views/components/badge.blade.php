@props([
    'variant' => 'neutral', // neutral, primary, success, warning, danger, info
    'size' => 'md', // sm, md, lg
    'dot' => false,
])

@php
$variants = [
    'neutral' => 'bg-slate-700/60 text-slate-300 border-slate-600/50',
    'primary' => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    'success' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
    'warning' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
    'danger'  => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
    'info'    => 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30',
    'purple'  => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
];

$sizes = [
    'sm' => 'text-[11px] px-2 py-0.5',
    'md' => 'text-xs px-2.5 py-1',
    'lg' => 'text-sm px-3 py-1.5',
];

$dotColors = [
    'neutral' => 'bg-slate-400',
    'primary' => 'bg-blue-400',
    'success' => 'bg-emerald-400',
    'warning' => 'bg-amber-400',
    'danger'  => 'bg-rose-400',
    'info'    => 'bg-cyan-400',
    'purple'  => 'bg-purple-400',
];

$variantClass = $variants[$variant] ?? $variants['neutral'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
$dotColor = $dotColors[$variant] ?? $dotColors['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-medium rounded-full border {$variantClass} {$sizeClass}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
    @endif
    {{ $slot }}
</span>
