@props([
    'totalEarnedPts' => 1000000,
    'totalRedeemedPts' => 650000,
    'totalExpiredPts' => 100000,
    'activeOutstandingPts' => 250000,
    'breakageRevenueIdr' => 10000000,
])

@php
    $redeemPct = $totalEarnedPts > 0 ? round(($totalRedeemedPts / $totalEarnedPts) * 100, 1) : 0;
    $expiredPct = $totalEarnedPts > 0 ? round(($totalExpiredPts / $totalEarnedPts) * 100, 1) : 0;
    $activePct = $totalEarnedPts > 0 ? round(($activeOutstandingPts / $totalEarnedPts) * 100, 1) : 0;
@endphp

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3 mb-5">
        <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>Dekomposisi Waterfall Liabilitas & Pendapatan Breakage (IFRS 15)</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Kekekalan Alokasi Poin: Poin Diterbitkan = Ditebus + Hangus Breakage + Aktif Beredar
            </p>
        </div>
        <div class="text-right">
            <span class="text-xs text-slate-400 block">Akumulasi Pendapatan Breakage</span>
            <span class="text-base font-black text-emerald-600 dark:text-emerald-400">Rp {{ number_format($breakageRevenueIdr, 0, ',', '.') }}</span>
        </div>
    </div>

    <!-- Waterfall Progress Stack -->
    <div class="mb-5">
        <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2">
            <span>Total Poin Kumulatif: {{ number_format($totalEarnedPts) }} PTS (100%)</span>
            <span>Nilai Neraca Bersih: Rp {{ number_format($activeOutstandingPts * 100, 0, ',', '.') }}</span>
        </div>
        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-xl h-6 p-1 flex space-x-1 border border-slate-200 dark:border-slate-700">
            <!-- Redeemed (Blue) -->
            <div class="bg-indigo-500 h-full rounded-lg transition-all relative group flex items-center justify-center text-[10px] text-white font-bold"
                 style="width: {{ $redeemPct }}%">
                <span class="truncate px-1">{{ $redeemPct }}% Ditebus</span>
            </div>
            <!-- Expired / Breakage (Emerald) -->
            <div class="bg-emerald-500 h-full rounded-lg transition-all relative group flex items-center justify-center text-[10px] text-white font-bold"
                 style="width: {{ $expiredPct }}%">
                <span class="truncate px-1">{{ $expiredPct }}% Breakage</span>
            </div>
            <!-- Active Outstanding (Amber) -->
            <div class="bg-amber-400 h-full rounded-lg transition-all relative group flex items-center justify-center text-[10px] text-slate-900 font-bold"
                 style="width: {{ $activePct }}%">
                <span class="truncate px-1">{{ $activePct }}% Aktif</span>
            </div>
        </div>
    </div>

    <!-- 3 Metrics Card Breakdown -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1 text-xs">
        <div class="p-4 rounded-xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40">
            <div class="flex items-center justify-between mb-1">
                <span class="text-indigo-900 dark:text-indigo-300 font-bold">1. Penebusan Hadiah</span>
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            </div>
            <div class="text-lg font-black text-slate-800 dark:text-slate-100">{{ number_format($totalRedeemedPts) }} PTS</div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Kamar gratis, F&B dining, tiket festival, miles aliansi.</p>
        </div>

        <div class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40">
            <div class="flex items-center justify-between mb-1">
                <span class="text-emerald-900 dark:text-emerald-300 font-bold">2. Pengakuan Breakage</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ number_format($totalExpiredPts) }} PTS</div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Poin hangus diakui ke akun pendapatan IFRS 15.</p>
        </div>

        <div class="p-4 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40">
            <div class="flex items-center justify-between mb-1">
                <span class="text-amber-900 dark:text-amber-300 font-bold">3. Liabilitas Beredar</span>
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </div>
            <div class="text-lg font-black text-slate-800 dark:text-slate-100">{{ number_format($activeOutstandingPts) }} PTS</div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Kewajiban aktif yang diproteksi cadangan kas likuid.</p>
        </div>
    </div>
</div>

