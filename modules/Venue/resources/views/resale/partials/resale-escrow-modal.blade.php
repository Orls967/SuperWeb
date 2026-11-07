<div id="resaleEscrowModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="escrowModalTitle">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="escrowModalTitle" class="text-lg font-bold text-slate-900 dark:text-white">Escrow Perlindungan Pembeli</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Jaminan Keaslian Tiket & Regulasi Cap 120%</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('resaleEscrowModal').classList.add('hidden')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300">
                    <span class="sr-only">Tutup</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-4 text-sm text-slate-600 dark:text-slate-300">
                <p>
                    Dana pembelian Anda akan ditahan secara aman di dalam rekening penampungan (Escrow) Superweb Venue dan hanya diteruskan ke penjual setelah sistem memverifikasi integritas tiket.
                </p>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Ketentuan Perlindungan Transaksi</h4>
                    <ul class="mt-2 space-y-2 text-xs">
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">✓</span>
                            <span>Barcode lama langsung dinonaktifkan permanen saat transaksi selesai (Zero Double-Entry).</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">✓</span>
                            <span>Barcode kriptografis baru diterbitkan secara eksklusif ke akun pembeli terdaftar.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">✓</span>
                            <span>Pajak PBJT 10% dan royalti promotor 2% dipotong otomatis demi kepatuhan regulasi daerah.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="font-bold text-rose-600 dark:text-rose-400">✕</span>
                            <span>Tiket sekunder tidak dapat dipindahtangankan kembali (Single Transfer Limit).</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                <button type="button" onclick="document.getElementById('resaleEscrowModal').classList.add('hidden')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    Mengerti
                </button>
            </div>
        </div>
    </div>
</div>

