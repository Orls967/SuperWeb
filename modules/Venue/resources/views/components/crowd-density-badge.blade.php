@props(['density' => 0.0, 'status' => 'SAFE'])

@php
    $density = (float) $density;
    $status = strtoupper((string) $status);
    
    if ($density >= 5.0 || $status === 'CRITICAL' || $status === 'CRUSH_RISK') {
        $badgeClass = 'bg-rose-500/20 text-rose-400 border-rose-500/40 animate-pulse';
        $dotClass = 'bg-rose-500 animate-ping';
        $label = 'Bahaya Kerumunan (Kritis)';
    } elseif ($density >= 4.0 || $status === 'HIGH' || $status === 'SURGE_WARNING') {
        $badgeClass = 'bg-amber-500/20 text-amber-400 border-amber-500/40';
        $dotClass = 'bg-amber-400';
        $label = 'Peringatan Padat';
    } elseif ($density >= 2.5 || $status === 'MODERATE') {
        $badgeClass = 'bg-sky-500/20 text-sky-400 border-sky-500/40';
        $dotClass = 'bg-sky-400';
        $label = 'Sedang Terkendali';
    } else {
        $badgeClass = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40';
        $dotClass = 'bg-emerald-400';
        $label = 'Aman Terbuka';
    }
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold border backdrop-blur-md $badgeClass"]) }}>
    <span class="relative flex h-2 w-2">
        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 {{ $dotClass }}"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 {{ str_replace('animate-ping', '', $dotClass) }}"></span>
    </span>
    <span>{{ $label }} ({{ number_format($density, 1) }} org/m²)</span>
</div>
