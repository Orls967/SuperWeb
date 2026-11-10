@props([
    'labels' => [],
    'values' => [],
    'width' => 600,
    'height' => 220,
    'barColor' => 'primary',
    'title' => null,
])

@php
$chart = \Modules\Shared\Application\Ui\SvgChartGeometry::buildBarChart($values, $labels, $width, $height);
$fillHex = match($barColor) {
    'success' => '#15803D',
    'danger' => '#B91C1C',
    default => '#1D4ED8',
};
@endphp

<div class="w-full space-y-2">
    @if($title)
        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $title }}</h4>
    @endif

    <div class="w-full overflow-x-auto">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-auto select-none" role="img" aria-label="Diagram Batang">
            {{-- Grid horizontal & Label Y --}}
            @foreach($chart['scale']['ticks'] as $tick)
                @php
                    $range = max(1, $chart['scale']['max'] - $chart['scale']['min']);
                    $normY = ($tick - $chart['scale']['min']) / $range;
                    $yPos = (int) round($height - 30 - ($normY * ($height - 60)));
                @endphp
                <line x1="40" y1="{{ $yPos }}" x2="{{ $width - 40 }}" y2="{{ $yPos }}" stroke="#E2E8F0" stroke-dasharray="4 4" class="dark:stroke-slate-700" />
                <text x="35" y="{{ $yPos + 4 }}" text-anchor="end" font-size="10" fill="#94A3B8" class="tabular-nums font-mono">{{ $tick }}</text>
            @endforeach

            {{-- Balok Batang --}}
            @foreach($chart['bars'] as $b)
                <rect
                    x="{{ $b['x'] }}"
                    y="{{ $b['y'] }}"
                    width="{{ $b['width'] }}"
                    height="{{ $b['height'] }}"
                    rx="4"
                    fill="{{ $fillHex }}"
                />
                <text x="{{ $b['x'] + ($b['width'] / 2) }}" y="{{ $b['y'] - 5 }}" text-anchor="middle" font-size="10" font-weight="600" fill="#475569" class="tabular-nums font-mono">{{ $b['value'] }}</text>
                <text x="{{ $b['x'] + ($b['width'] / 2) }}" y="{{ $height - 12 }}" text-anchor="middle" font-size="10" fill="#64748B">{{ $b['label'] }}</text>
            @endforeach
        </svg>
    </div>
</div>
