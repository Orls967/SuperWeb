@props([
    'tier' => 'SILVER',
    'nights' => 0,
    'spendIdr' => 0,
    'ptsBalance' => 0,
    'progressPercentage' => 0.0,
])

@php
    $nextTier = match(strtoupper($tier)) {
        'SILVER' => 'GOLD',
        'GOLD' => 'PLATINUM',
        'PLATINUM' => 'BLACK',
        default => 'MAX_TIER',
    };

    $targetNights = match(strtoupper($tier)) {
        'SILVER' => 20,
        'GOLD' => 40,
        'PLATINUM' => 75,
        default => 75,
    };

    $targetSpend = match(strtoupper($tier)) {
        'SILVER' => 35000000,
        'GOLD' => 100000000,
        'PLATINUM' => 250000000,
        default => 250000000,
    };

    $nightsPct = min(100, round(($nights / max(1, $targetNights)) * 100, 1));
    $spendPct = min(100, round(($spendIdr / max(1, $targetSpend)) * 100, 1));
@endphp

<div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-800/40"
     x-data="{ showDetails: false, animateGauge: true }">
    <div class="flex items-center justify-between border-b border-indigo-800/40 pb-4 mb-5">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-400/40 flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold tracking-tight">Kualifikasi Tingkat Keanggotaan Global</h3>
                <p class="text-xs text-indigo-300">Evaluasi Jendela Bergerak 365 Hari (Rolling Window Evaluation)</p>
            </div>
        </div>
        <span class="px-3.5 py-1.5 rounded-full text-xs font-black tracking-widest uppercase border
            @if(strtoupper($tier) === 'BLACK') bg-zinc-950 text-amber-300 border-amber-500/60 shadow-lg shadow-amber-500/10
            @elseif(strtoupper($tier) === 'PLATINUM') bg-slate-800 text-cyan-300 border-cyan-400/60
            @elseif(strtoupper($tier) === 'GOLD') bg-amber-900/60 text-amber-200 border-amber-400/50
            @else bg-slate-800 text-slate-300 border-slate-700 @endif">
            {{ $tier }} TIER
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <!-- SVG Radar Circular Progress -->
        <div class="flex flex-col items-center justify-center p-4 bg-slate-950/40 rounded-xl border border-indigo-900/50">
            <div class="relative w-36 h-36 flex items-center justify-center">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <!-- Background circle -->
                    <circle cx="50" cy="50" r="42" stroke="currentColor" stroke-width="8" class="text-slate-800" fill="transparent"/>
                    <!-- Progress circle -->
                    <circle cx="50" cy="50" r="42" stroke="currentColor" stroke-width="8"
                            class="@if(strtoupper($tier) === 'BLACK') text-amber-400 @elseif(strtoupper($tier) === 'PLATINUM') text-cyan-400 @else text-indigo-400 @endif"
                            fill="transparent"
                            stroke-dasharray="264"
                            stroke-dashoffset="{{ 264 - (264 * ($progressPercentage / 100)) }}"
                            stroke-linecap="round"
                            style="transition: stroke-dashoffset 1s ease-in-out;"/>
                </svg>
                <div class="absolute flex flex-col items-center justify-center text-center">
                    <span class="text-2xl font-black">{{ round($progressPercentage, 0) }}%</span>
                    <span class="text-[10px] tracking-wider text-slate-400 uppercase">Menuju {{ $nextTier }}</span>
                </div>
            </div>
            <div class="mt-3 text-center">
                <span class="text-xs text-indigo-300 font-medium">Batas Waktu: Rolling 365 Hari</span>
            </div>
        </div>

        <!-- Metrics Bars -->
        <div class="col-span-2 space-y-5">
            <div>
                <div class="flex justify-between items-center text-xs mb-1.5 font-semibold">
                    <span class="text-slate-300 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span>Akumulasi Malam Menginap (Room Nights)</span>
                    </span>
                    <span class="text-indigo-300">{{ $nights }} / {{ $targetNights }} Malam ({{ $nightsPct }}%)</span>
                </div>
                <div class="w-full bg-slate-950/60 rounded-full h-3 p-0.5 border border-indigo-950">
                    <div class="bg-gradient-to-r from-indigo-500 to-cyan-400 h-2 rounded-full transition-all duration-700"
                         style="width: {{ $nightsPct }}%"></div>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center text-xs mb-1.5 font-semibold">
                    <span class="text-slate-300 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Total Pembelanjaan Terkonsolidasi (17 Lini)</span>
                    </span>
                    <span class="text-emerald-400">Rp {{ number_format($spendIdr, 0, ',', '.') }} / Rp {{ number_format($targetSpend, 0, ',', '.') }}</span>
                </div>
                <div class="w-full bg-slate-950/60 rounded-full h-3 p-0.5 border border-indigo-950">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2 rounded-full transition-all duration-700"
                         style="width: {{ $spendPct }}%"></div>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-between text-xs text-indigo-300">
                <div class="flex items-center space-x-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Status Proteksi Soft-Landing: Aktif</span>
                </div>
                <button type="button" @click="showDetails = !showDetails" class="underline hover:text-white transition">
                    <span x-text="showDetails ? 'Tutup Rincian' : 'Lihat Syarat Kualifikasi'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Collapsible Detailed Matrix -->
    <div x-show="showDetails" x-transition.duration.300ms class="mt-6 pt-5 border-t border-indigo-800/40 text-xs space-y-3">
        <h4 class="font-bold text-slate-200">Ketentuan Kualifikasi Berdasarkan KONSEP 11F & Algoritma H13:</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
            <div class="bg-slate-900/60 p-2.5 rounded-lg border border-slate-800">
                <div class="font-bold text-slate-300">SILVER</div>
                <div class="text-[10px] text-slate-400">Registrasi Dasar</div>
                <div class="mt-1 font-semibold text-indigo-300">Diskon 0%</div>
            </div>
            <div class="bg-slate-900/60 p-2.5 rounded-lg border border-slate-800">
                <div class="font-bold text-amber-300">GOLD</div>
                <div class="text-[10px] text-slate-400">20 Malam / Rp 35 Jt</div>
                <div class="mt-1 font-semibold text-amber-300">Diskon 5%</div>
            </div>
            <div class="bg-slate-900/60 p-2.5 rounded-lg border border-slate-800">
                <div class="font-bold text-cyan-300">PLATINUM</div>
                <div class="text-[10px] text-slate-400">40 Malam / Rp 100 Jt</div>
                <div class="mt-1 font-semibold text-cyan-300">Diskon 10%</div>
            </div>
            <div class="bg-slate-900/60 p-2.5 rounded-lg border border-slate-800">
                <div class="font-bold text-amber-400">BLACK</div>
                <div class="text-[10px] text-slate-400">75 Malam / Rp 250 Jt</div>
                <div class="mt-1 font-semibold text-amber-400">Diskon 15%</div>
            </div>
        </div>
    </div>
</div>

