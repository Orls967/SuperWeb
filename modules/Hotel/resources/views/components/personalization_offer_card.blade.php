@props([
    'campaignCode' => 'CAMP-BALI-01',
    'destinationTitle' => 'Resor Tropis Nusa Dua & Beach Club VIP',
    'packageTitle' => 'Bali Summer Sunset VIP Getaway',
    'pointsDiscounted' => 25000,
    'affinityScore' => 0.9500,
    'validUntil' => null,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between"
     x-data="{ showModal: false }">
    <div>
        <div class="flex items-center justify-between mb-3">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                Skor Kesesuaian: {{ round($affinityScore * 100) }}%
            </span>
            <span class="text-[11px] text-slate-400 font-mono">{{ $campaignCode }}</span>
        </div>

        <h4 class="text-base font-bold text-slate-900 dark:text-white mb-1.5 leading-snug">
            {{ $packageTitle }}
        </h4>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            Destinasi: <strong class="text-slate-700 dark:text-slate-300">{{ $destinationTitle }}</strong>
        </p>
    </div>

    <div>
        <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-3 border border-slate-100 dark:border-slate-800 mb-4 flex items-center justify-between text-xs">
            <div>
                <span class="text-slate-400 block text-[10px]">Tarif Poin Spesial</span>
                <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">{{ number_format($pointsDiscounted) }} PTS</span>
            </div>
            <div class="text-right">
                <span class="text-slate-400 block text-[10px]">Masa Berlaku</span>
                <span class="font-semibold text-slate-700 dark:text-slate-300">
                    {{ $validUntil ? $validUntil->format('d M Y') : '30 Hari' }}
                </span>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <button type="button" @click="showModal = true"
                    class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition text-center shadow-sm">
                Klaim Penawaran Ini
            </button>
            <button type="button" class="p-2 border border-slate-300 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 transition" title="Abaikan Rekomendasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Booking Confirmation Modal -->
    <div x-show="showModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800"
             @click.away="showModal = false">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Konfirmasi Penebusan Paket Rekomendasi</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                Poin sebesar <strong class="text-indigo-600">{{ number_format($pointsDiscounted) }} PTS</strong> akan didebit dari Travel Pass Anda secara instan dengan metode FIFO.
            </p>
            <div class="flex justify-end space-x-2 text-xs">
                <button type="button" @click="showModal = false" class="px-4 py-2 border rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                <button type="button" @click="showModal = false" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700">Setuju & Tebus</button>
            </div>
        </div>
    </div>
</div>

