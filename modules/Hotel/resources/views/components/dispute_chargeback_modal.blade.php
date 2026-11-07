@props(['lineId' => null])

<div class="bg-white dark:bg-slate-900 shadow-md rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6" x-data="{
    disputeReason: 'TAMU_TIDAK_MENGAKUI_ITEM',
    targetPayer: 'CORPORATE',
    notes: '',
    isSubmitting: false,
    submitDispute() {
        this.isSubmitting = true;
        setTimeout(() => {
            alert('Sengketa baris folio berhasil diajukan dan dipindahkan ke antrean audit.');
            this.isSubmitting = false;
        }, 600);
    }
}">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                Proses Sengketa (Dispute) & Chargeback Baris Tagihan Folio
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Isolasi item yang disengketakan dari saldo checkout tamu dan arahkan rekonsiliasi ke sub-folio yang sesuai.</p>
        </div>
        <span class="px-2.5 py-1 text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 rounded-full">
            Prosedur Front Office SOP-HTL-92
        </span>
    </div>

    <!-- Form Fields -->
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Alasan Sengketa / Klaim Tamu</label>
            <select x-model="disputeReason" class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200">
                <option value="TAMU_TIDAK_MENGAKUI_ITEM">Tamu Menolak Penggunaan Minibar / Telephone</option>
                <option value="SALAH_POSTING_KAMAR">Salah Posting Nomor Kamar dari Outlet POS</option>
                <option value="HARGA_TIDAK_SESUAI_KONTRAK">Harga Tidak Sesuai Voucher / Kesepakatan Korporat</option>
                <option value="LAYANAN_TIDAK_MEMUASKAN">Keluhan Kualitas Layanan (Service Defect Diskon)</option>
                <option value="TAGIHAN_GANDA">Duplikasi Posting Tagihan yang Sama</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Arahkan Beban Pengganti (Reallocation Target)</label>
            <div class="grid grid-cols-3 gap-3">
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="target_payer" value="CORPORATE" x-model="targetPayer" class="text-indigo-600">
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Beban Korporat (AR)</span>
                </label>
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="target_payer" value="HOTEL_ALLOWANCE" x-model="targetPayer" class="text-indigo-600">
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Allowance GM Hotel</span>
                </label>
                <label class="flex items-center gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800/40">
                    <input type="radio" name="target_payer" value="DISPUTE_HOLD" x-model="targetPayer" class="text-indigo-600">
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Hold Rekonsiliasi POS</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Catatan Tambahan & Bukti Pendukung</label>
            <textarea x-model="notes" rows="3" placeholder="Masukkan catatan tanda tangan tamu, slip outlet, atau nomor tiket insiden..." class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-200"></textarea>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
        <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition">
            Batal
        </button>
        <button type="button" @click="submitDispute()" :disabled="isSubmitting" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition shadow-sm disabled:opacity-50">
            <span x-show="!isSubmitting">Ajukan Dispute & Pisahkan dari Folio</span>
            <span x-show="isSubmitting">Memproses Alokasi...</span>
        </button>
    </div>
</div>
