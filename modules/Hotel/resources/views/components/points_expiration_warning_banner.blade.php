@props([
    'expiringPoints' => 5000,
    'daysRemaining' => 18,
    'batchNumber' => 'BTH-202410-0921',
])

@if($expiringPoints > 0 && $daysRemaining <= 30)
<div class="bg-gradient-to-r from-amber-500/15 via-rose-500/10 to-amber-500/15 border border-amber-300 dark:border-amber-800/80 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4"
     x-data="{ dismissed: false }" x-show="!dismissed" x-transition>
    <div class="flex items-center space-x-3.5">
        <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg flex-shrink-0">
            ⚠️
        </div>
        <div>
            <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <span>Perhatian: Sebagian Poin Anda Akan Kedaluwarsa!</span>
                <span class="px-2 py-0.5 bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 rounded text-[10px] font-black uppercase">
                    {{ $daysRemaining }} Hari Lagi
                </span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                Sebesar <strong class="text-rose-600 dark:text-rose-400">{{ number_format($expiringPoints) }} PTS</strong> pada batch <code class="text-xs font-mono">{{ $batchNumber }}</code> akan hangus dan diakui sebagai breakage jika tidak ditebus sebelum batas waktu.
            </p>
        </div>
    </div>

    <div class="flex items-center space-x-2.5 flex-shrink-0 text-xs font-semibold">
        <a href="{{ route('hotel.travel_pass.award_booking') }}"
           class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl transition shadow-sm">
            Tukarkan Sekarang
        </a>
        <button type="button" @click="dismissed = true" class="p-2 text-slate-400 hover:text-slate-600" title="Tutup">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
@endif

