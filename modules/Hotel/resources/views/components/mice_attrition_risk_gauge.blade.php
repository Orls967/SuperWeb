@props([
    'contractedNights' => 200,
    'pickedUpNights' => 140,
    'tolerancePercent' => 15.0,
    'penaltyRate' => 1250000.0,
])

@php
    $pickupPct = $contractedNights > 0 ? round(($pickedUpNights / $contractedNights) * 100, 1) : 0;
    $minCommitment = (int) ceil($contractedNights * (1 - ($tolerancePercent / 100.0)));
    $shortfall = max(0, $minCommitment - $pickedUpNights);
    $estimatedPenalty = $shortfall * $penaltyRate;
    $isAtRisk = $shortfall > 0;
@endphp

<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-5">
    <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-700 mb-3">
        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 flex items-center space-x-2">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>MICE Room Block Slippage & Attrition Gauge</span>
        </h4>
        @if($isAtRisk)
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 animate-pulse">
                Attrition Risk: Shortfall {{ $shortfall }} Nights
            </span>
        @else
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                Commitment Fulfilled ({{ $pickupPct }}%)
            </span>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 my-2 text-center">
        <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
            <div class="text-xs text-zinc-500">Contracted Nights</div>
            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 font-mono">{{ $contractedNights }}</div>
        </div>
        <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
            <div class="text-xs text-zinc-500">Minimum Pickup (85%)</div>
            <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ $minCommitment }}</div>
        </div>
        <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
            <div class="text-xs text-zinc-500">Actual Picked Up</div>
            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 font-mono">{{ $pickedUpNights }}</div>
        </div>
        <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
            <div class="text-xs text-zinc-500">Estimated Penalty</div>
            <div class="text-lg font-bold {{ $isAtRisk ? 'text-red-600 dark:text-red-400' : 'text-zinc-400' }} font-mono">
                IDR {{ number_format($estimatedPenalty, 0) }}
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="mt-4">
        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1">
            <span>Pickup Progress</span>
            <span class="font-medium font-mono">{{ $pickupPct }}% (Target: {{ 100 - $tolerancePercent }}%)</span>
        </div>
        <div class="w-full bg-zinc-100 dark:bg-zinc-700 h-2.5 rounded-full overflow-hidden">
            <div class="h-2.5 rounded-full transition-all duration-500 {{ $pickupPct >= (100 - $tolerancePercent) ? 'bg-emerald-500' : 'bg-amber-500' }}"
                 style="width: {{ min(100, $pickupPct) }}%"></div>
        </div>
    </div>
</div>
