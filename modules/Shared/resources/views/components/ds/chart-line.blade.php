@props([
    'labels' => [],
    'values' => [],
    'width' => 600,
    'height' => 220,
    'strokeColor' => 'primary',
    'title' => null,
])

@php
$chart = \Modules\Shared\Application\Ui\SvgChartGeometry::buildLineChart($values, $labels, $width, $height);
$strokeHex = match($strokeColor) {
    'success' => '#15803D',
    'danger' => '#B91C1C',
    default => '#1D4ED8',
};
$fillHex = match($strokeColor) {
    'success' => 'rgba(21, 128, 61, 0.1)',
    'danger' => 'rgba(185, 28, 28, 0.1)',
    default => 'rgba(29, 78, 216, 0.1)',
};
@endphp

<div class="w-full space-y-2">
    @if($title)
        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $title }}</h4>
    @endif

    <div class="w-full overflow-x-auto">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-auto select-none" role="img" aria-label="Grafik Garis">
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

            {{-- Area Fill --}}
            @if($chart['area_d'] !== '')
                <path d="{{ $chart['area_d'] }}" fill="{{ $fillHex }}" />
            @endif

            {{-- Jalur Garis --}}
            @if($chart['path_d'] !== '')
                <path d="{{ $chart['path_d'] }}" fill="none" stroke="{{ $strokeHex }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            @endif

            {{-- Titik Koordinat & Label X --}}
            @foreach($chart['points'] as $pt)
                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4" fill="{{ $strokeHex }}" stroke="#FFFFFF" stroke-width="2" class="dark:stroke-slate-900" />
                <text x="{{ $pt['x'] }}" y="{{ $height - 12 }}" text-anchor="middle" font-size="10" fill="#64748B">{{ $pt['label'] }}</text>
            @endforeach
        </svg>
    </div>
</div>
