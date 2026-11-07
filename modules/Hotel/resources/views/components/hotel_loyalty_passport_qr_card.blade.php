@props(['guest' => null])

<div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl shadow-xl border border-indigo-800/40 p-6 space-y-6 relative overflow-hidden">
    <!-- Background Watermark Glow -->
    <div class="absolute -right-8 -top-8 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>

    <div class="flex items-center justify-between relative z-10 pb-4 border-b border-indigo-800/40">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-600/30 border border-indigo-500/30 rounded-xl">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004 11a7.962 7.962 0 00.99 3.868"/>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold tracking-tight">Superweb Global Passport QR Token</h3>
                <p class="text-xs text-indigo-300">Identifikasi Tamu Nirsentuh & Akses Executive Lounge Multi-Properti</p>
            </div>
        </div>
        <span class="px-3 py-1 text-xs font-mono font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30 rounded-full">
            PLATINUM ELITE
        </span>
    </div>

    <!-- QR & Card Body -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center relative z-10">
        <!-- Digital QR Simulated Canvas -->
        <div class="flex flex-col items-center bg-white p-4 rounded-xl shadow-inner">
            <div class="w-32 h-32 bg-slate-900 rounded-lg p-2 flex flex-col justify-between">
                <div class="flex justify-between">
                    <div class="w-6 h-6 border-4 border-white bg-slate-900"></div>
                    <div class="w-6 h-6 border-4 border-white bg-slate-900"></div>
                </div>
                <div class="text-[9px] font-mono text-center text-indigo-400 tracking-tighter">QR-PASS-2026</div>
                <div class="flex justify-between">
                    <div class="w-6 h-6 border-4 border-white bg-slate-900"></div>
                    <div class="w-3 h-3 bg-white"></div>
                </div>
            </div>
            <span class="text-[10px] font-mono text-slate-600 mt-2 font-bold tracking-wider">HASH-7782-9901-PL</span>
        </div>

        <!-- Member Metadata -->
        <div class="md:col-span-2 space-y-3 text-xs">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <span class="text-indigo-400 block text-[11px]">Nama Pemegang Kartu:</span>
                    <span class="font-bold text-slate-100 text-sm">Bpk. Hendrawan Santoso</span>
                </div>
                <div>
                    <span class="text-indigo-400 block text-[11px]">Nomor Passport ID:</span>
                    <span class="font-mono font-bold text-slate-100 text-sm">PASSPORT-GST-9201</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-indigo-900/60">
                <div>
                    <span class="text-indigo-400 block text-[11px]">Malam Menginap Tahun Ini:</span>
                    <span class="font-bold text-emerald-400">28 Malam Terverifikasi</span>
                </div>
                <div>
                    <span class="text-indigo-400 block text-[11px]">Saldo Poin Reward:</span>
                    <span class="font-bold text-amber-400">42.500 Poin</span>
                </div>
            </div>

            <p class="text-[11px] text-indigo-300/80 italic pt-1">
                Tunjukkan kode QR dinamis ini kepada petugas lounge atau meja resepsionis untuk verifikasi instan.
            </p>
        </div>
    </div>
</div>
