@php
    $level = (int) ($level ?? 0);
@endphp

@if($level === 1)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30" title="Eskalasi Level 1 (Supervisor)">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 mr-1.5"></span>
        Eskalasi Lvl 1
    </span>
@elseif($level === 2)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-orange-500/20 text-orange-300 border border-orange-500/30" title="Eskalasi Level 2 (Manager)">
        <span class="w-1.5 h-1.5 rounded-full bg-orange-400 mr-1.5"></span>
        Eskalasi Lvl 2
    </span>
@elseif($level === 3)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30" title="Eskalasi Level 3 (Direktur)">
        <span class="w-1.5 h-1.5 rounded-full bg-rose-400 mr-1.5 animate-ping"></span>
        Eskalasi Lvl 3
    </span>
@elseif($level >= 4)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30" title="Eskalasi Level 4 (Eksekutif/Direksi)">
        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 mr-1.5 animate-pulse"></span>
        Eskalasi Lvl 4 Kritis
    </span>
@endif

