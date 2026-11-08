@props([
    'propertyTitle' => 'Grand Nusantara Resort Bali',
    'roomTypeTitle' => 'Deluxe Ocean View',
    'pointsRequired' => 18000,
    'userPointsBalance' => 35000,
])

<div x-data="{ open: false, agreeTerms: false }" class="inline-block">
    <button type="button" @click="open = true"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition shadow-md">
        Pesan dengan Poin
    </button>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 text-left"
             @click.away="open = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold">
                        🏨
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Konfirmasi Pemesanan Award Night</h3>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="space-y-3.5 text-xs text-slate-600 dark:text-slate-400 mb-6">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Properti Hotel:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $propertyTitle }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Tipe Kamar:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $roomTypeTitle }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Poin Dibutuhkan:</span>
                        <span class="font-black text-indigo-600 dark:text-indigo-400">{{ number_format($pointsRequired) }} PTS</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Sisa Saldo Anda Setelah Transaksi:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ number_format($userPointsBalance - $pointsRequired) }} PTS</span>
                    </div>
                </div>

                <div class="flex items-start space-x-2 pt-1">
                    <input type="checkbox" id="agreeTerms" x-model="agreeTerms" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5">
                    <label for="agreeTerms" class="text-[11px] text-slate-500">
                        Saya menyetujui pemotongan poin FIFO dan ketentuan pembatalan award night (gratis hingga H-3 kedatangan).
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 text-xs">
                <button type="button" @click="open = false" class="px-4 py-2 border rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800">
                    Batal
                </button>
                <button type="button" :disabled="!agreeTerms"
                        :class="agreeTerms ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-slate-300 dark:bg-slate-700 text-slate-500 cursor-not-allowed'"
                        class="px-5 py-2 font-bold rounded-xl transition shadow-md">
                    Konfirmasi & Terbitkan Voucher
                </button>
            </div>
        </div>
    </div>
</div>

