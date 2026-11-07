@props([
    'stationName' => 'Bar Counter Main',
    'score' => 88.5,
    'waitSeconds' => 45,
    'queueLength' => 6,
    'staffCount' => 4
])

@php
    $score = (float) $score;
    $waitSeconds = (int) $waitSeconds;
    $isSaturated = ($waitSeconds > 180 || $score < 50.0);
    $colorClass = $score >= 80.0 ? 'text-emerald-400 stroke-emerald-500' : ($score >= 60.0 ? 'text-amber-400 stroke-amber-500' : 'text-rose-400 stroke-rose-500');
    $bgRing = $score >= 80.0 ? 'border-emerald-500/20' : ($score >= 60.0 ? 'border-amber-500/20' : 'border-rose-500/20');
@endphp

<div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 backdrop-blur-md relative overflow-hidden">
    @if($isSaturated)
        <div class="absolute -right-8 -top-8 w-24 h-24 bg-rose-500/20 rounded-full blur-xl pointer-events-none"></div>
    @endif

    <div class="flex items-center justify-between mb-3">
        <h4 class="font-bold text-white text-sm tracking-wide">{{ $stationName }}</h4>
        <span class="text-xs font-mono px-2 py-0.5 rounded-md border {{ $isSaturated ? 'bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse' : 'bg-slate-700/40 text-slate-300 border-slate-600/40' }}">
            {{ $staffCount }} Bartender Aktif
        </span>
    </div>

    <div class="flex items-center gap-4 my-2">
        <div class="relative w-16 h-16 flex-shrink-0 flex items-center justify-center rounded-full border-4 {{ $bgRing }}">
            <span class="text-xl font-black font-mono {{ explode(' ', $colorClass)[0] }}">
                {{ (int)$score }}
            </span>
            <span class="absolute -bottom-2 text-[10px] font-bold text-slate-400 uppercase tracking-tighter">WPS</span>
        </div>
        <div class="space-y-1">
            <div class="text-xs text-slate-400">
                Estimasi Waktu Tunggu: <strong class="text-white font-mono">{{ $waitSeconds }} detik</strong>
            </div>
            <div class="text-xs text-slate-400">
                Antrean Kamera: <strong class="text-amber-400 font-mono">{{ $queueLength }} tamu</strong>
            </div>
            <div class="text-[11px] {{ $isSaturated ? 'text-rose-400 font-bold' : 'text-emerald-400' }}">
                {{ $isSaturated ? 'Saturasi Antrean! Butuh Reinforcement.' : 'Layanan Lancar Sesuai SLA.' }}
            </div>
        </div>
    </div>
</div>
