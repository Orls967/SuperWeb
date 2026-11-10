<div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
        <h3 class="text-xs font-semibold text-amber-300 uppercase tracking-wider flex items-center space-x-2">
            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>Panduan Penanganan Kueri 0-Result</span>
        </h3>
        <span class="text-[11px] text-slate-400">Rekomendasi Kurasi</span>
    </div>

    <div class="space-y-3 text-xs text-slate-300">
        <div class="flex items-start space-x-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-[10px]">1</span>
            <p>
                <strong class="text-white">Tambahkan Padanan Sinonim:</strong> Jika kueri adalah istilah sehari-hari (misal: "nota", "faktur", "mobil"), daftarkan ke <code>SearchSynonymExpander</code> agar otomatis dipetakan ke leksikon teknis.
            </p>
        </div>

        <div class="flex items-start space-x-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-[10px]">2</span>
            <p>
                <strong class="text-white">Verifikasi Izin RBAC:</strong> Dokumen rahasia disembunyikan secara deterministik untuk pengguna tanpa izin. Periksa apakah pengguna memiliki hak akses yang memadai.
            </p>
        </div>

        <div class="flex items-start space-x-2.5">
            <span class="flex-shrink-0 w-5 h-5 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-[10px]">3</span>
            <p>
                <strong class="text-white">Jalankan Sinkronisasi Indeks:</strong> Jika data baru saja ditambahkan di modul spesifik, jalankan perintah <code>php artisan search:reindex --module=TARGET</code>.
            </p>
        </div>
    </div>
</div>

