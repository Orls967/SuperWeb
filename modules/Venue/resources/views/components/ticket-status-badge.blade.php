@props([
    'status' => 'VALID',
    'size' => 'md',
])

@php
    $statusUpper = strtoupper((string) $status);
    $configs = [
        'VALID' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500/30', 'icon' => '✓', 'label' => 'Valid & Sah'],
        'ISSUED' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500/30', 'icon' => '✓', 'label' => 'Diterbitkan'],
        'LISTED' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-300', 'border' => 'border-amber-500/30', 'icon' => '●', 'label' => 'Sedang Tayang'],
        'ESCROW_HOLD' => ['bg' => 'bg-indigo-500/20', 'text' => 'text-indigo-300', 'border' => 'border-indigo-500/30', 'icon' => '🔒', 'label' => 'Escrow Hold'],
        'SETTLED' => ['bg' => 'bg-teal-500/20', 'text' => 'text-teal-300', 'border' => 'border-teal-500/30', 'icon' => '✓', 'label' => 'Selesai (Settled)'],
        'CANCELLED' => ['bg' => 'bg-slate-700/50', 'text' => 'text-slate-400', 'border' => 'border-slate-600', 'icon' => '✕', 'label' => 'Dibatalkan'],
        'EXPIRED' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'border' => 'border-rose-500/30', 'icon' => '⏱', 'label' => 'Kedaluwarsa'],
        'OFFERED' => ['bg' => 'bg-cyan-500/20', 'text' => 'text-cyan-300', 'border' => 'border-cyan-500/30', 'icon' => '★', 'label' => 'Ditawarkan (15m)'],
        'WAITING' => ['bg' => 'bg-slate-800', 'text' => 'text-slate-300', 'border' => 'border-slate-700', 'icon' => '⏳', 'label' => 'Dalam Antrean'],
        'CLAIMED' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-300', 'border' => 'border-emerald-500/30', 'icon' => '✓', 'label' => 'Terklaim'],
        'BLOCKED' => ['bg' => 'bg-rose-950/60', 'text' => 'text-rose-300', 'border' => 'border-rose-500/40', 'icon' => '🚫', 'label' => 'Diblokir (Bot)'],
    ];

    $cfg = $configs[$statusUpper] ?? ['bg' => 'bg-slate-800', 'text' => 'text-slate-400', 'border' => 'border-slate-700', 'icon' => '•', 'label' => $statusUpper];
    $sizeClasses = match($size) {
        'sm' => 'px-2 py-0.5 text-[10px]',
        'lg' => 'px-3 py-1.5 text-xs',
        default => 'px-2.5 py-0.5 text-[11px]',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full font-bold uppercase tracking-wider border {{ $cfg['bg'] }} {{ $cfg['text'] }} {{ $cfg['border'] }} {{ $sizeClasses }}">
    <span>{{ $cfg['icon'] }}</span>
    <span>{{ $cfg['label'] }}</span>
</span>

