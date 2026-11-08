@props([
    'booths' => [],
    'hallName' => 'Exhibition Hall A'
])

<div class="bg-slate-900/60 border border-slate-700/80 rounded-xl p-4 space-y-3">
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">{{ $hallName }}</h4>
        <span class="text-xs text-slate-400 font-mono">{{ count($booths) }} Stan Terdaftar</span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 font-mono text-xs">
        @forelse($booths as $booth)
            @php
                $isOccupied = ($booth['status'] ?? '') === 'CONFIRMED';
            @endphp
            <div class="p-2 rounded-lg border text-center space-y-1 {{ $isOccupied ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-300' : 'bg-slate-800/80 border-slate-700 text-slate-400' }}">
                <div class="font-bold text-[11px]">{{ $booth['booth_number'] ?? 'Stan' }}</div>
                <div class="text-[9px] text-slate-400 font-sans">{{ $booth['booth_type'] ?? '3x3m' }}</div>
                <div class="text-[9px] truncate text-slate-300 font-sans">{{ $booth['client_name'] ?? 'Tersedia' }}</div>
            </div>
        @empty
            <div class="col-span-full py-4 text-center text-xs text-slate-400 font-sans">
                Tidak ada denah stan pameran aktif.
            </div>
        @endforelse
    </div>
</div>

