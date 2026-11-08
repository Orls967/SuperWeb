@props([
    'initialRooms',
    'allocatedRooms',
    'confirmedPickup',
    'cutoffTier' => 'PROTECTED'
])

@php
    $unpicked = max(0, $allocatedRooms - $confirmedPickup);
    $pickupPercent = $allocatedRooms > 0 ? round(($confirmedPickup / $allocatedRooms) * 100, 1) : 0;
@endphp

<div class="space-y-2">
    <div class="flex items-center justify-between text-xs text-slate-300">
        <div>
            <span>Terkonfirmasi: <strong class="text-white">{{ $confirmedPickup }}</strong></span>
            <span class="text-slate-400"> / Alokasi: {{ $allocatedRooms }} Kamar</span>
        </div>
        <div class="font-mono">
            <span class="text-emerald-400 font-bold">{{ $pickupPercent }}%</span>
            <span class="text-slate-400 text-[10px]"> (Sisa Unpicked: {{ $unpicked }})</span>
        </div>
    </div>

    <div class="w-full bg-slate-700 h-2.5 rounded-full overflow-hidden flex">
        <div class="bg-emerald-500 h-full transition-all duration-300" style="width: {{ min(100, $pickupPercent) }}%" title="Pickup: {{ $confirmedPickup }}"></div>
        <div class="bg-amber-500/60 h-full transition-all duration-300" style="width: {{ max(0, 100 - $pickupPercent) }}%" title="Unpicked: {{ $unpicked }}"></div>
    </div>

    <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
        <span>Tahap Cutoff: <strong class="text-slate-200 font-mono">{{ $cutoffTier }}</strong></span>
        <span>Kamar Awal Kontrak: <strong class="text-white font-mono">{{ $initialRooms }}</strong></span>
    </div>
</div>

