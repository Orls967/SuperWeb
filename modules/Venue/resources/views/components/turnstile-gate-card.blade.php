@props([
    'gateName' => 'Gate Alpha 01',
    'status' => 'NORMAL',
    'mode' => 'BIOMETRIC_AND_BARCODE',
    'scansPerMinute' => 24,
    'totalScanned' => 842,
    'hardwareHealth' => 'HEALTHY'
])

@php
    $status = strtoupper((string) $status);
    $health = strtoupper((string) $hardwareHealth);
    
    $isEmergency = ($status === 'FAIL_SAFE_FREE' || $status === 'EMERGENCY_OPEN');
    $isLocked = ($status === 'LOCKED_INGRESS_RESTRICTED');
@endphp

<div class="rounded-2xl border p-5 transition-all duration-300 {{ $isEmergency ? 'bg-rose-950/40 border-rose-500/60 shadow-lg shadow-rose-950/50' : ($isLocked ? 'bg-amber-950/30 border-amber-500/40' : 'bg-slate-800/80 border-slate-700/80 hover:border-slate-600') }}">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-xl {{ $isEmergency ? 'bg-rose-500/20 text-rose-300' : 'bg-slate-700/60 text-sky-400' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                    </svg>
                </span>
                <div>
                    <h4 class="text-base font-bold text-white tracking-wide">{{ $gateName }}</h4>
                    <span class="text-xs text-slate-400 font-mono">{{ $mode }}</span>
                </div>
            </div>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider {{ $isEmergency ? 'bg-rose-500 text-white font-extrabold animate-pulse' : ($isLocked ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30') }}">
            {{ $status }}
        </span>
    </div>

    <div class="grid grid-cols-2 gap-3 py-3 border-y border-slate-700/60 my-3">
        <div>
            <span class="text-xs text-slate-400 block mb-0.5">Throughput Laju</span>
            <span class="text-lg font-bold text-white font-mono">{{ $scansPerMinute }} <span class="text-xs font-normal text-slate-400">org/menit</span></span>
        </div>
        <div>
            <span class="text-xs text-slate-400 block mb-0.5">Total Lewat</span>
            <span class="text-lg font-bold text-emerald-400 font-mono">{{ number_format($totalScanned) }}</span>
        </div>
    </div>

    <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
        <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full {{ $health === 'HEALTHY' ? 'bg-emerald-400' : 'bg-amber-400 animate-ping' }}"></span>
            Hardware {{ $health }}
        </span>
        <button type="button" class="text-sky-400 hover:text-sky-300 font-medium hover:underline transition-colors">
            Kalibrasi IoT &rarr;
        </button>
    </div>
</div>
