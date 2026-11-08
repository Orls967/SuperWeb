@props([
    'partnerCode' => 'GARUDA_AIR',
    'status' => 'ONLINE',
    'latencyMs' => 124,
    'lastSyncTimestamp' => null,
])

<div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-xs shadow-sm">
    <span class="w-2 h-2 rounded-full @if($status === 'ONLINE') bg-emerald-500 animate-pulse @else bg-rose-500 @endif"></span>
    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $partnerCode }}</span>
    <span class="text-slate-400">·</span>
    <span class="text-slate-500 font-mono">{{ $latencyMs }}ms</span>
    <span class="text-[10px] px-1.5 py-0.5 rounded font-black uppercase
        @if($status === 'ONLINE') bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300
        @else bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 @endif">
        {{ $status }}
    </span>
</div>

