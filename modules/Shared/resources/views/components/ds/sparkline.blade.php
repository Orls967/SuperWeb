@props([
    'data' => [],
    'width' => 120,
    'height' => 32,
    'color' => 'primary', // primary, success, danger
])

@php
$pathD = \Modules\Shared\Application\Ui\SparklinePath::generate($data, $width, $height);
$strokeColor = match($color) {
    'success' => '#15803D',
    'danger' => '#B91C1C',
    default => '#1D4ED8',
};
@endphp

<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" class="overflow-visible" aria-hidden="true">
    @if($pathD !== '')
        <path
            d="{{ $pathD }}"
            fill="none"
            stroke="{{ $strokeColor }}"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    @endif
</svg>
