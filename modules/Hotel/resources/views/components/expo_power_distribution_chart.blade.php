@props([
    'totalSubPanels' => 4,
    'totalLoadKva' => 18.5,
    'capacityKva' => 45.0
])

@php
    $ratio = $capacityKva > 0 ? min(100, round(($totalLoadKva / $capacityKva) * 100, 1)) : 0;
@endphp

<div class="p-4 bg-slate-900/60 border border-slate-700 rounded-xl space-y-3 font-sans">
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider">Distribusi Daya Sub-Panel Ekshibisi</h4>
        <span class="text-xs text-emerald-400 font-mono font-bold">{{ $totalLoadKva }} kVA Beban Terukur</span>
    </div>

    <div class="space-y-2">
        <div class="flex justify-between text-xs text-slate-400">
            <span>Kapasitas Transformator Aman (80% Rating):</span>
            <span class="text-white font-mono">{{ $ratio }}%</span>
        </div>
        <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
            <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: {{ $ratio }}%"></div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-400 pt-1">
        <div>Sub-Panel Lantai: <strong class="text-slate-200">{{ $totalSubPanels }} Unit Aktif</strong></div>
        <div class="text-right">Frekuensi Grid: <strong class="text-slate-200 font-mono">50.0 Hz Stabil</strong></div>
    </div>
</div>
