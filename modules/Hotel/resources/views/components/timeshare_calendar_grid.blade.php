@props(['weeks', 'totalFractions'])

<div class="space-y-3">
    <div class="flex items-center justify-between text-xs">
        <span class="font-bold text-slate-800">Kalender Alokasi 52 Minggu Hak Inap</span>
        <div class="flex items-center gap-3 text-[11px]">
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-500"></span> Owner In-House</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-500"></span> Rental Pool</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-slate-200"></span> Belum Dialokasikan</span>
        </div>
    </div>
    <div class="grid grid-cols-13 gap-1.5 p-3 bg-slate-50 rounded-xl border border-slate-200">
        @for($w = 1; $w <= 52; $w++)
            @php
                $status = $weeks[$w] ?? 'UNALLOCATED';
                $color = match($status) {
                    'OWNER_OCCUPIED' => 'bg-emerald-500 text-white font-bold',
                    'RENTAL_POOL' => 'bg-amber-500 text-white font-bold',
                    default => 'bg-white border border-slate-200 text-slate-400',
                };
            @endphp
            <div class="h-8 rounded-md flex items-center justify-center text-[10px] {{ $color }}" title="Minggu {{ $w }}: {{ $status }}">
                {{ $w }}
            </div>
        @endfor
    </div>
</div>
