@props([
    'passNumber' => 'TP-9921-8842',
    'memberName' => 'Sutan Iskandar Syah',
    'tier' => 'BLACK',
    'ptsBalance' => 85000,
    'enrolledDate' => '10/24',
])

<div class="relative w-full max-w-sm h-56 rounded-3xl p-6 text-white overflow-hidden shadow-2xl transition transform hover:scale-[1.02] duration-300 border
    @if(strtoupper($tier) === 'BLACK') bg-gradient-to-tr from-zinc-950 via-slate-900 to-black border-amber-500/40 shadow-amber-500/10
    @elseif(strtoupper($tier) === 'PLATINUM') bg-gradient-to-tr from-slate-900 via-cyan-950 to-slate-950 border-cyan-400/40 shadow-cyan-500/10
    @elseif(strtoupper($tier) === 'GOLD') bg-gradient-to-tr from-amber-950 via-yellow-950 to-slate-950 border-amber-400/40 shadow-amber-500/10
    @else bg-gradient-to-tr from-slate-800 via-indigo-950 to-slate-900 border-indigo-400/30 @endif">

    <!-- Holographic Wave Background Effect -->
    <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-gradient-to-br from-white/10 to-transparent blur-2xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col justify-between h-full">
        <!-- Top bar: Logo & NFC Chip -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="text-xs font-black tracking-widest uppercase text-white/90">SUPERWEB HOTEL GLOBAL</span>
            </div>
            <!-- NFC Icon -->
            <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
        </div>

        <!-- EMV Chip simulation -->
        <div class="w-11 h-8 rounded-md bg-gradient-to-r from-amber-200 to-yellow-400 border border-yellow-500/80 shadow-inner flex items-center justify-center">
            <div class="w-8 h-5 border border-yellow-600/50 rounded-sm"></div>
        </div>

        <!-- Middle: Pass Number & Tier Badge -->
        <div class="flex items-center justify-between">
            <div class="font-mono text-base tracking-widest font-black drop-shadow-md text-white/95">
                {{ $passNumber }}
            </div>
            <span class="px-2.5 py-0.5 rounded text-[10px] font-black tracking-wider uppercase border
                @if(strtoupper($tier) === 'BLACK') bg-amber-400/20 text-amber-300 border-amber-400/40
                @elseif(strtoupper($tier) === 'PLATINUM') bg-cyan-400/20 text-cyan-300 border-cyan-400/40
                @elseif(strtoupper($tier) === 'GOLD') bg-amber-400/20 text-amber-200 border-amber-400/40
                @else bg-slate-700/40 text-slate-300 border-slate-600 @endif">
                {{ $tier }}
            </span>
        </div>

        <!-- Bottom: Member Name & Points Balance -->
        <div class="flex items-end justify-between border-t border-white/10 pt-3">
            <div>
                <span class="text-[9px] tracking-wider text-white/60 block uppercase">NAMA ANGGOTA</span>
                <span class="text-xs font-bold tracking-wide uppercase text-white/90">{{ $memberName }}</span>
            </div>
            <div class="text-right">
                <span class="text-[9px] tracking-wider text-white/60 block uppercase">SALDO POIN PTS</span>
                <span class="text-sm font-black text-amber-300">{{ number_format($ptsBalance) }} PTS</span>
            </div>
        </div>
    </div>
</div>

