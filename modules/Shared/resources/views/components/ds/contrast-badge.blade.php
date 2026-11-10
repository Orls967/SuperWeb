@props([
    'ratioBps', // e.g. 45422
    'colorA' => null,
    'colorB' => null,
])

@php
$ratio = $ratioBps / 10000.0;
$formattedRatio = number_format($ratio, 2, ',', '.').':1';
$passesAa = $ratioBps >= 45000;
$passesAaa = $ratioBps >= 70000;

if ($passesAaa) {
    $level = 'AAA';
    $badgeClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-300';
} elseif ($passesAa) {
    $level = 'AA';
    $badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 border-blue-300';
} else {
    $level = 'GAGAL';
    $badgeClass = 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300 border-red-300';
}
@endphp

<div class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-mono {{ $badgeClass }}">
    <span class="font-bold">{{ $level }}</span>
    <span class="tabular-nums font-semibold">({{ $formattedRatio }})</span>
    @if($colorA && $colorB)
        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-sans hidden sm:inline">
            {{ $colorA }} / {{ $colorB }}
        </span>
    @endif
</div>

