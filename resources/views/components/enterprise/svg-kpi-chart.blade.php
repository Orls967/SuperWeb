@props([
    'type' => 'sparkline',
    'title' => 'KPI Metrik',
    'subtitle' => null,
    'value' => null,
    'change' => null,
    'trend' => 'up',
    'color' => 'indigo',
    'data' => [35, 52, 48, 70, 65, 88, 96],
    'height' => 160,
    'width' => '100%',
    'unit' => '',
    'id' => null,
])

@php
    $chartId = $id ?? 'chart_' . md5($type . $title . uniqid('', true));
    
    // Color palette definitions
    $palettes = [
        'indigo' => [
            'from' => '#6366f1',
            'to' => '#8b5cf6',
            'fill' => '#4f46e5',
            'text' => 'text-indigo-400',
            'bg' => 'bg-indigo-500/10',
            'border' => 'border-indigo-500/30',
        ],
        'emerald' => [
            'from' => '#10b981',
            'to' => '#059669',
            'fill' => '#059669',
            'text' => 'text-emerald-400',
            'bg' => 'bg-emerald-500/10',
            'border' => 'border-emerald-500/30',
        ],
        'amber' => [
            'from' => '#f59e0b',
            'to' => '#d97706',
            'fill' => '#d97706',
            'text' => 'text-amber-400',
            'bg' => 'bg-amber-500/10',
            'border' => 'border-amber-500/30',
        ],
        'rose' => [
            'from' => '#f43f5e',
            'to' => '#e11d48',
            'fill' => '#e11d48',
            'text' => 'text-rose-400',
            'bg' => 'bg-rose-500/10',
            'border' => 'border-rose-500/30',
        ],
        'cyan' => [
            'from' => '#06b6d4',
            'to' => '#0284c7',
            'fill' => '#0284c7',
            'text' => 'text-cyan-400',
            'bg' => 'bg-cyan-500/10',
            'border' => 'border-cyan-500/30',
        ],
        'purple' => [
            'from' => '#a855f7',
            'to' => '#7c3aed',
            'fill' => '#7c3aed',
            'text' => 'text-purple-400',
            'bg' => 'bg-purple-500/10',
            'border' => 'border-purple-500/30',
        ],
        'blue' => [
            'from' => '#3b82f6',
            'to' => '#1d4ed8',
            'fill' => '#1d4ed8',
            'text' => 'text-blue-400',
            'bg' => 'bg-blue-500/10',
            'border' => 'border-blue-500/30',
        ],
    ];

    $palette = $palettes[$color] ?? $palettes['indigo'];
    
    // Normalize data values
    $rawSeries = is_array($data) ? $data : [35, 52, 48, 70, 65, 88, 96];
    $labels = [];
    $values = [];
    foreach ($rawSeries as $k => $v) {
        $labels[] = is_string($k) ? $k : 'T' . ($k + 1);
        $values[] = floatval($v);
    }
    if (empty($values)) {
        $values = [10, 20, 30, 40, 50];
        $labels = ['T1', 'T2', 'T3', 'T4', 'T5'];
    }

    $minVal = min($values);
    $maxVal = max($values);
    $range = ($maxVal - $minVal) ?: 1.0;
    $count = count($values);
@endphp

<div class="relative rounded-2xl bg-slate-900/80 border border-slate-700/60 p-5 backdrop-blur-xl shadow-lg hover:border-slate-600/80 transition-all group overflow-hidden">
    {{-- Card Header --}}
    <div class="flex items-start justify-between gap-4 mb-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {{ $trend === 'down' ? 'bg-rose-400' : 'bg-emerald-400' }} animate-pulse"></span>
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">{{ $title }}</h4>
            </div>
            @if($value !== null)
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold font-mono text-white tracking-tight">{{ $value }}</span>
                    @if($unit)
                        <span class="text-xs text-slate-400 font-medium">{{ $unit }}</span>
                    @endif
                </div>
            @endif
            @if($subtitle)
                <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">{{ $subtitle }}</p>
            @endif
        </div>

        @if($change !== null)
            <div class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold {{ $trend === 'down' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' }}">
                @if($trend === 'down')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                @elseif($trend === 'up')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                @endif
                <span>{{ $change }}</span>
            </div>
        @endif
    </div>

    {{-- Pure SVG Visualization Rendering --}}
    <div class="w-full flex items-center justify-center">
        @if($type === 'sparkline')
            @php
                $w = 320;
                $h = 90;
                $padX = 14;
                $padY = 12;
                $plotW = $w - ($padX * 2);
                $plotH = $h - ($padY * 2);

                $pts = [];
                for ($i = 0; $i < $count; $i++) {
                    $x = $padX + ($count > 1 ? ($i / ($count - 1)) * $plotW : $plotW / 2);
                    $y = $h - $padY - (($values[$i] - $minVal) / $range) * $plotH;
                    $pts[] = ['x' => round($x, 1), 'y' => round($y, 1), 'val' => $values[$i]];
                }

                $lineD = '';
                foreach ($pts as $idx => $p) {
                    $lineD .= ($idx === 0 ? 'M ' : ' L ') . "{$p['x']} {$p['y']}";
                }
                $lastPt = end($pts);
                $firstPt = $pts[0];
                $areaD = $lineD . " L {$lastPt['x']} " . ($h - 2) . " L {$firstPt['x']} " . ($h - 2) . " Z";
            @endphp
            <svg class="w-full overflow-visible" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="height: {{ $height }}px;">
                <defs>
                    <linearGradient id="area_{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="{{ $palette['from'] }}" stop-opacity="0.45" />
                        <stop offset="100%" stop-color="{{ $palette['to'] }}" stop-opacity="0.0" />
                    </linearGradient>
                    <linearGradient id="line_{{ $chartId }}" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="{{ $palette['from'] }}" />
                        <stop offset="100%" stop-color="{{ $palette['to'] }}" />
                    </linearGradient>
                    <filter id="glow_{{ $chartId }}" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="{{ $palette['from'] }}" flood-opacity="0.5"/>
                    </filter>
                </defs>

                {{-- Subtle Guide Lines --}}
                <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $w - $padX }}" y2="{{ $padY }}" stroke="#334155" stroke-width="0.75" stroke-dasharray="3,3" />
                <line x1="{{ $padX }}" y1="{{ $h / 2 }}" x2="{{ $w - $padX }}" y2="{{ $h / 2 }}" stroke="#334155" stroke-width="0.75" stroke-dasharray="3,3" />
                <line x1="{{ $padX }}" y1="{{ $h - $padY }}" x2="{{ $w - $padX }}" y2="{{ $h - $padY }}" stroke="#334155" stroke-width="0.75" />

                {{-- Area & Curve --}}
                <path d="{{ $areaD }}" fill="url(#area_{{ $chartId }})" />
                <path d="{{ $lineD }}" fill="none" stroke="url(#line_{{ $chartId }})" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" filter="url(#glow_{{ $chartId }})" />

                {{-- Vertices --}}
                @foreach($pts as $p)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="#0f172a" stroke="{{ $palette['from'] }}" stroke-width="2" class="transition-transform group-hover:scale-125" />
                @endforeach

                {{-- Beacon Pulse at latest point --}}
                <circle cx="{{ $lastPt['x'] }}" cy="{{ $lastPt['y'] }}" r="6" fill="{{ $palette['from'] }}" opacity="0.3" class="animate-ping" />
                <circle cx="{{ $lastPt['x'] }}" cy="{{ $lastPt['y'] }}" r="4" fill="{{ $palette['to'] }}" />
            </svg>

        @elseif($type === 'bar')
            @php
                $w = 340;
                $h = 110;
                $padX = 16;
                $padY = 22;
                $plotW = $w - ($padX * 2);
                $plotH = $h - ($padY * 2);
                $barSlot = $count > 0 ? $plotW / $count : 40;
                $barW = max(8, min(28, $barSlot * 0.65));
            @endphp
            <svg class="w-full" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="height: {{ $height }}px;">
                <defs>
                    <linearGradient id="barGrad_{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="{{ $palette['from'] }}" />
                        <stop offset="100%" stop-color="{{ $palette['to'] }}" stop-opacity="0.7" />
                    </linearGradient>
                </defs>

                {{-- Horizontal Grid --}}
                <line x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $w - $padX }}" y2="{{ $padY }}" stroke="#334155" stroke-width="0.75" stroke-dasharray="3,3" />
                <line x1="{{ $padX }}" y1="{{ $h - $padY }}" x2="{{ $w - $padX }}" y2="{{ $h - $padY }}" stroke="#475569" stroke-width="1" />

                @foreach($values as $idx => $v)
                    @php
                        $barH = max(4, ($v / ($maxVal ?: 1)) * $plotH);
                        $x = $padX + ($idx * $barSlot) + (($barSlot - $barW) / 2);
                        $y = $h - $padY - $barH;
                    @endphp
                    <g class="cursor-pointer transition-opacity hover:opacity-90">
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $barH }}" rx="4" fill="url(#barGrad_{{ $chartId }})" />
                        <text x="{{ $x + ($barW / 2) }}" y="{{ $h - 6 }}" fill="#94a3b8" font-size="9" text-anchor="middle" font-family="monospace">{{ $labels[$idx] ?? 'T' . ($idx+1) }}</text>
                        <text x="{{ $x + ($barW / 2) }}" y="{{ max(12, $y - 4) }}" fill="#e2e8f0" font-size="8" text-anchor="middle" font-weight="600">{{ is_int($v) ? $v : number_format($v, 0) }}</text>
                    </g>
                @endforeach
            </svg>

        @elseif($type === 'waterfall')
            @php
                $w = 340;
                $h = 110;
                $padX = 14;
                $padY = 22;
                $plotW = $w - ($padX * 2);
                $plotH = $h - ($padY * 2);
                $slot = $count > 0 ? $plotW / $count : 40;
                $barW = max(10, min(24, $slot * 0.65));

                $running = 0;
                $maxCumulative = max(1, array_sum(array_map('abs', $values)));
            @endphp
            <svg class="w-full" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="height: {{ $height }}px;">
                <defs>
                    <linearGradient id="posGrad_{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10b981" />
                        <stop offset="100%" stop-color="#059669" />
                    </linearGradient>
                    <linearGradient id="negGrad_{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#f43f5e" />
                        <stop offset="100%" stop-color="#be123c" />
                    </linearGradient>
                    <linearGradient id="totGrad_{{ $chartId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6366f1" />
                        <stop offset="100%" stop-color="#4f46e5" />
                    </linearGradient>
                </defs>

                <line x1="{{ $padX }}" y1="{{ $h - $padY }}" x2="{{ $w - $padX }}" y2="{{ $h - $padY }}" stroke="#475569" stroke-width="1" />

                @foreach($values as $idx => $v)
                    @php
                        $isFirst = ($idx === 0);
                        $isLast = ($idx === $count - 1);
                        $isNegative = ($v < 0);

                        $barH = max(4, (abs($v) / $maxCumulative) * $plotH * 1.6);
                        $barH = min($plotH, $barH);

                        if ($isFirst || $isLast) {
                            $fill = "url(#totGrad_{$chartId})";
                            $y = $h - $padY - $barH;
                            $running = $v;
                        } elseif ($isNegative) {
                            $fill = "url(#negGrad_{$chartId})";
                            $y = $h - $padY - $barH - (max(0, $running + $v) / $maxCumulative * $plotH);
                            $running += $v;
                        } else {
                            $fill = "url(#posGrad_{$chartId})";
                            $y = $h - $padY - (max(0, $running) / $maxCumulative * $plotH) - $barH;
                            $running += $v;
                        }
                        $x = $padX + ($idx * $slot) + (($slot - $barW) / 2);
                        $y = max(10, min($h - $padY - $barH, $y));
                    @endphp
                    <g>
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $barH }}" rx="3" fill="{{ $fill }}" />
                        <text x="{{ $x + ($barW / 2) }}" y="{{ $h - 6 }}" fill="#94a3b8" font-size="8" text-anchor="middle" font-family="monospace">{{ $labels[$idx] ?? 'S' . ($idx+1) }}</text>
                    </g>
                @endforeach
            </svg>

        @elseif($type === 'radial')
            @php
                $pct = min(100.0, max(0.0, floatval(preg_replace('/[^0-9.]/', '', (string)($value ?? $values[0] ?? 75)))));
                $r = 54;
                $circ = 2 * M_PI * $r; // ~339.29
                $offset = $circ - (($pct / 100.0) * $circ);
            @endphp
            <div class="relative flex items-center justify-center my-2" style="height: {{ $height }}px;">
                <svg class="w-40 h-40 transform -rotate-90 overflow-visible" viewBox="0 0 140 140">
                    <defs>
                        <linearGradient id="radialGrad_{{ $chartId }}" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="{{ $palette['from'] }}" />
                            <stop offset="100%" stop-color="{{ $palette['to'] }}" />
                        </linearGradient>
                    </defs>
                    {{-- Track --}}
                    <circle cx="70" cy="70" r="{{ $r }}" stroke="#1e293b" stroke-width="10" fill="none" />
                    {{-- Progress --}}
                    <circle cx="70" cy="70" r="{{ $r }}" stroke="url(#radialGrad_{{ $chartId }})" stroke-width="10" stroke-linecap="round" fill="none" stroke-dasharray="{{ round($circ, 2) }}" stroke-dashoffset="{{ round($offset, 2) }}" class="transition-all duration-700 ease-out" />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                    <span class="text-2xl font-black font-mono text-white tracking-tighter">{{ round($pct, 1) }}%</span>
                    <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Kapasitas</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Subtle footer tag --}}
    <div class="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[10px] text-slate-500 font-mono">
        <span>Vektor SVG Murni</span>
        <span class="text-slate-400">0 Eksternal CDN</span>
    </div>
</div>
