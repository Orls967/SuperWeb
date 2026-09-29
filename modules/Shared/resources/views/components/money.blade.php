@props([
    'value' => null,
    'amount' => 0,
    'asset' => 'IDR',
])

@php
if ($value instanceof \Modules\Shared\Domain\ValueObjects\Money) {
    $formatted = $value->format();
} elseif ($asset === 'IDR') {
    $num = is_numeric($amount) ? (float) $amount : 0;
    $formatted = 'Rp ' . number_format($num, 0, ',', '.');
} else {
    $num = is_numeric($amount) ? (float) $amount : 0;
    $formatted = rtrim(rtrim(number_format($num, 8, '.', ''), '0'), '.') . ' ' . $asset;
}
@endphp

<span {{ $attributes->merge(['class' => 'font-semibold tracking-tight']) }}>
    {{ $formatted }}
</span>
