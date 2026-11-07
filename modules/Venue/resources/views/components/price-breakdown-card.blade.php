@props([
    'askingPrice' => 0,
    'faceValue' => 0,
    'taxBps' => 1000,
    'platformBps' => 800,
    'promoterBps' => 200,
])

@php
    $askingPrice = (int) $askingPrice;
    $faceValue = (int) $faceValue;
    $taxPbjt = intdiv($askingPrice * (int) $taxBps, 10000);
    $platformFee = intdiv($askingPrice * (int) $platformBps, 10000);
    $promoterRoyalty = intdiv($askingPrice * (int) $promoterBps, 10000);
    $sellerPayout = $askingPrice - ($taxPbjt + $platformFee + $promoterRoyalty);
    $markupPercent = $faceValue > 0 ? (int) (($askingPrice - $faceValue) * 100 / $faceValue) : 0;
@endphp

<div class="bg-slate-900/90 rounded-2xl p-5 border border-slate-700/80 space-y-4 shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <div>
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">Rincian Finansial & Pajak PBJT</h4>
            <p class="text-[11px] text-slate-500 mt-0.5">Berdasarkan Perda Pajak Hiburan & Regulasi Anti-Scalping</p>
        </div>
        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">
            CONSERVED IDR
        </span>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="p-3 bg-slate-800/80 rounded-xl border border-slate-700/50">
            <span class="text-[10px] text-slate-400">Pajak PBJT (10%)</span>
            <div class="text-sm font-bold text-indigo-300 mt-1">Rp {{ number_format($taxPbjt, 0, ',', '.') }}</div>
            <span class="text-[9px] text-slate-500">Kas Pemda Badung</span>
        </div>

        <div class="p-3 bg-slate-800/80 rounded-xl border border-slate-700/50">
            <span class="text-[10px] text-slate-400">Platform Escrow (8%)</span>
            <div class="text-sm font-bold text-cyan-300 mt-1">Rp {{ number_format($platformFee, 0, ',', '.') }}</div>
            <span class="text-[9px] text-slate-500">Layanan Kliring Sah</span>
        </div>

        <div class="p-3 bg-slate-800/80 rounded-xl border border-slate-700/50">
            <span class="text-[10px] text-slate-400">Royalti Artis (2%)</span>
            <div class="text-sm font-bold text-amber-300 mt-1">Rp {{ number_format($promoterRoyalty, 0, ',', '.') }}</div>
            <span class="text-[9px] text-slate-500">Kreator & Promotor</span>
        </div>

        <div class="p-3 bg-emerald-950/40 rounded-xl border border-emerald-500/30">
            <span class="text-[10px] text-emerald-400 font-bold">Pencairan Bersih (80%)</span>
            <div class="text-sm font-black text-emerald-300 mt-1">Rp {{ number_format($sellerPayout, 0, ',', '.') }}</div>
            <span class="text-[9px] text-emerald-500">Rekening Penjual</span>
        </div>
    </div>

    @if($faceValue > 0)
        <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
            <span>Perbandingan terhadap Harga Asli:</span>
            <span class="font-bold {{ $markupPercent <= 20 ? 'text-emerald-400' : 'text-rose-400' }}">
                Rp {{ number_format($faceValue, 0, ',', '.') }} → Rp {{ number_format($askingPrice, 0, ',', '.') }} (+{{ $markupPercent }}%)
            </span>
        </div>
    @endif
</div>

