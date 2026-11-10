@props([
    'data' => [],
    'width' => 120,
    'height' => 32,
    'color' => 'primary', // primary, success, danger
])

@php
$pathD = \Modules\Shared\Application\Ui\SparklinePath::generate($data, $width, $height);
$strokeColor = match($color) {
    'success' => 'var(--ds-color-success, currentColor)',
    'danger' => 'var(--ds-color-danger, currentColor)',
    default => 'var(--ds-color-primary, currentColor)',
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
