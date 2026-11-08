@props([
    'tier' => 'GOLD',
    'nights' => 24,
    'spendIdr' => 42000000,
    'ptsBalance' => 18500,
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
    <div>
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold">
                    ⭐
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">Status Keanggotaan Saat Ini</h4>
                    <span class="text-[11px] text-slate-400">Proteksi Soft-Landing Aktif</span>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-md text-xs font-black uppercase
                @if($tier === 'BLACK') bg-zinc-950 text-amber-300 border border-amber-500/50
                @elseif($tier === 'PLATINUM') bg-cyan-950 text-cyan-300 border border-cyan-500/50
                @elseif($tier === 'GOLD') bg-amber-950 text-amber-300 border border-amber-500/50
                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 @endif">
                {{ $tier }}
            </span>
        </div>

        <div class="space-y-3 text-xs mb-4">
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Malam Menginap Terverifikasi:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ $nights }} Malam</span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Total Belanja Ekosistem:</span>
                <span class="font-bold text-slate-900 dark:text-white font-mono">Rp {{ number_format($spendIdr, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-slate-600 dark:text-slate-400">
                <span>Saldo Poin yang Siap Dipakai:</span>
                <span class="font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ number_format($ptsBalance) }} PTS</span>
            </div>
        </div>
    </div>

    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center space-x-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <span>Semua Benefit Aktif</span>
        </span>
        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
            Lihat Keistimewaan &rarr;
        </button>
    </div>
</div>

