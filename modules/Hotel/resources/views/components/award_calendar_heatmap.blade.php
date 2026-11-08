@props([
    'propertyReference' => 'PROP-BALI-01',
    'basePoints' => 15000,
    'marginalCostIdr' => 300000,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
     x-data="{
        selectedDate: null,
        occupancyFilter: 'ALL',
        calculatePoints(occupancy) {
            let sigmoid = 1.0 / (1.0 + Math.exp(-10.0 * (occupancy - 0.70)));
            let surge = 1.0 + (1.5 * sigmoid);
            let pts = Math.ceil({{ $basePoints }} * surge);
            let floorPts = Math.ceil(({{ $marginalCostIdr }} * 1.15) / 100);
            return Math.max(pts, floorPts);
        }
     }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4 mb-4">
        <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Kalender Tarif Poin Award Night Dinamis</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Kurva Respons Okupansi Sigmoid & Guardrail Floor 115% (Algoritma H11)
            </p>
        </div>

        <!-- Legend -->
        <div class="flex items-center space-x-3 text-xs">
            <span class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                <span class="text-slate-600 dark:text-slate-400">Off-Peak (&lt;60%)</span>
            </span>
            <span class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span>
                <span class="text-slate-600 dark:text-slate-400">Standar (60-80%)</span>
            </span>
            <span class="flex items-center space-x-1.5">
                <span class="w-3 h-3 rounded-full bg-rose-500 inline-block"></span>
                <span class="text-slate-600 dark:text-slate-400">Peak (&gt;80%)</span>
            </span>
        </div>
    </div>

    <!-- Calendar Grid Simulation (14 Days Ahead) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 mb-6">
        @for($day = 1; $day <= 14; $day++)
            @php
                $simulatedOcc = min(0.98, max(0.35, 0.40 + (($day % 5) * 0.12) + ($day > 7 ? 0.10 : 0)));
                $isPeak = $simulatedOcc >= 0.80;
                $isMedium = $simulatedOcc >= 0.60 && $simulatedOcc < 0.80;
            @endphp
            <div class="p-3 rounded-xl border text-center transition cursor-pointer hover:shadow-md
                @if($isPeak) bg-rose-50/60 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/40 hover:border-rose-400
                @elseif($isMedium) bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/40 hover:border-amber-400
                @else bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/40 hover:border-emerald-400 @endif"
                @click="selectedDate = 'Hari +{{ $day }} (Okupansi: {{ round($simulatedOcc * 100) }}%)'">
                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    {{ now()->addDays($day)->format('D, d M') }}
                </div>
                <div class="text-sm font-black
                    @if($isPeak) text-rose-600 dark:text-rose-400
                    @elseif($isMedium) text-amber-600 dark:text-amber-400
                    @else text-emerald-600 dark:text-emerald-400 @endif">
                    {{ number_format(ceil($basePoints * (1 + (1.5 / (1 + exp(-10 * ($simulatedOcc - 0.70))))))) }} PTS
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    Okupansi: {{ round($simulatedOcc * 100) }}%
                </div>
            </div>
        @endfor
    </div>

    <!-- Active Selection Info Banner -->
    <div x-show="selectedDate" x-transition.opacity class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between text-xs gap-3">
        <div class="flex items-center space-x-2 text-indigo-900 dark:text-indigo-200 font-medium">
            <svg class="w-5 h-5 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Tanggal Terpilih: <strong x-text="selectedDate"></strong>. Batas minimum floor: <strong>3.450 PTS (Rp 345.000)</strong> terproteksi penuh.</span>
        </div>
        <button type="button" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition">
            Pesan Kamar Ini
        </button>
    </div>
</div>

