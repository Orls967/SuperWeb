@props(['guest' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6" x-data="{
    campaignType: 'WIN_BACK_VOUCHER',
    discountPercent: 25,
    roomUpgrade: true,
    freeAirportTransfer: true,
    directGmLetter: true,
    bonusPoints: 5000,
    isDispatching: false,
    dispatchIntervention() {
        this.isDispatching = true;
        setTimeout(() => {
            alert('Protokol intervensi retensi tamu berhasil diaktifkan. Penawaran resmi telah dikirim ke tamu.');
            this.isDispatching = false;
        }, 700);
    }
}">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Protokol Intervensi Retensi Tamu VIP (Algoritma Churn H9)
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Konfigurasi paket penawaran penyelamatan khusus untuk mencegah atrisi tamu berpenghasilan tinggi (*High LTV*).</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 rounded-full">
            Risiko Churn: 78.5% (Tinggi)
        </span>
    </div>

    <!-- Intervention Options Form -->
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pilihan Strategi Kampanye Penyelamatan</label>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="campaign_type" value="WIN_BACK_VOUCHER" x-model="campaignType" class="text-rose-600">
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Win-Back Stay Voucher</div>
                        <div class="text-[11px] text-slate-400">Diskon menginap akhir pekan</div>
                    </div>
                </label>
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="campaign_type" value="APOLOGY_EXPERIENCE" x-model="campaignType" class="text-rose-600">
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Apology Dinner & Spa</div>
                        <div class="text-[11px] text-slate-400">Pemulihan atas keluhan masa lalu</div>
                    </div>
                </label>
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="campaign_type" value="TIER_PRESERVATION" x-model="campaignType" class="text-rose-600">
                    <div>
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Tier Grace Extension</div>
                        <div class="text-[11px] text-slate-400">Perpanjangan status Platinum 1 thn</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Custom Parameters -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Persentase Diskon Kamar (%)</label>
                <input type="number" x-model.number="discountPercent" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Bonus Poin Reward Instan</label>
                <input type="number" x-model.number="bonusPoints" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
            </div>
        </div>

        <!-- Inclusions Checkboxes -->
        <div class="space-y-2 pt-2">
            <span class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Tambahan Fasilitas Eksklusif Tanpa Biaya:</span>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                    <input type="checkbox" x-model="roomUpgrade" class="rounded text-rose-600">
                    <span>Complimentary Room Upgrade</span>
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                    <input type="checkbox" x-model="freeAirportTransfer" class="rounded text-rose-600">
                    <span>Antar-Jemput Alphard Bandara</span>
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                    <input type="checkbox" x-model="directGmLetter" class="rounded text-rose-600">
                    <span>Surat Apresiasi Tanda Tangan GM</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition">
            Tutup Dialog
        </button>
        <button type="button" @click="dispatchIntervention()" :disabled="isDispatching" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition shadow-sm disabled:opacity-50">
            <span x-show="!isDispatching">Kirimkan Penawaran Win-Back Resmi</span>
            <span x-show="isDispatching">Mengirimkan Pesan Personal...</span>
        </button>
    </div>
</div>
