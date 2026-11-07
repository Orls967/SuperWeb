@props([
    'title' => 'Promo Spesial On-Site',
    'discountPercent' => 20,
    'category' => 'BEVERAGE',
    'expiryMinutes' => 30
])

<div class="bg-gradient-to-r from-purple-900/40 via-indigo-900/40 to-slate-900/80 border border-purple-500/40 rounded-2xl p-5 shadow-xl backdrop-blur-md relative overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-3 rounded-xl bg-purple-500/20 text-purple-300 font-black text-xl">
                {{ $discountPercent }}%
            </span>
            <div>
                <span class="text-[11px] font-mono uppercase tracking-wider text-purple-300 font-bold block">{{ $category }} EXCLUSIVE</span>
                <h4 class="text-base font-bold text-white">{{ $title }}</h4>
                <p class="text-xs text-slate-400 mt-0.5">Tunjukkan barcode aplikasi ke kasir bar sebelum waktu berakhir.</p>
            </div>
        </div>
        <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-2">
            <span class="text-xs font-mono text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20">
                Sisa {{ $expiryMinutes }} Menit
            </span>
            <button type="button" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs transition-colors shadow-lg shadow-purple-950/50">
                Gunakan Voucher &rarr;
            </button>
        </div>
    </div>
</div>
