@props(['unit' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6" x-data="{
    senderShareCount: 60,
    recipientEmail: 'investor.partner@resort.id',
    sharesToTransfer: 10,
    transferReason: 'GIFT_FAMILY',
    notarizationFeeIdr: 1500000,
    isProcessing: false,
    submitTransfer() {
        this.isProcessing = true;
        setTimeout(() => {
            alert('Pengalihan lembar token fraksional berhasil diverifikasi dan didaftarkan pada buku besar sertifikat kepemilikan.');
            this.isProcessing = false;
        }, 600);
    }
}">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                </svg>
                Pengalihan Saham Fraksional Vila (Fractional Token Deed Transfer)
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Proses hibah, penjualan sekunder, atau pengalihan waris atas lembar token kepemilikan vila resor.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 rounded-full">
            Legal Notarized Deed
        </span>
    </div>

    <!-- Form Transfer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Email / Akun Penerima Pengalihan</label>
            <input type="email" x-model="recipientEmail" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
        </div>

        <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Jumlah Lembar Token yang Dialihkan</label>
            <input type="number" x-model.number="sharesToTransfer" max="60" min="1" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
            <span class="text-[11px] text-slate-400">Kepemilikan saat ini: 60 Lembar Token (60.0%)</span>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Alasan Pengalihan Hak</label>
            <select x-model="transferReason" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
                <option value="SECONDARY_SALE">Penjualan Komersial Pasar Sekunder</option>
                <option value="GIFT_FAMILY">Hibah Keluarga Inti</option>
                <option value="INHERITANCE">Pengalihan Hak Waris</option>
                <option value="CORPORATE_RESTRUCTURING">Restrukturisasi Aset Perusahaan</option>
            </select>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Biaya Administrasi & Legalisasi Notaris</label>
            <div class="px-3 py-2 bg-slate-100 dark:bg-slate-800 rounded-lg font-mono font-bold text-slate-800 dark:text-slate-200">
                IDR 1.500.000 (Dipotong dari Rekening Pemilik)
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition">
            Batal
        </button>
        <button type="button" @click="submitTransfer()" :disabled="isProcessing" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition shadow-sm disabled:opacity-50">
            <span x-show="!isProcessing">Otorisasi & Eksekusi Pengalihan Saham</span>
            <span x-show="isProcessing">Mendaftarkan Sertifikat Baru...</span>
        </button>
    </div>
</div>
