@extends('layouts.app')

@section('title', 'Pembiayaan HODL-to-Drive')
@section('subtitle', 'Cicilan mobil dengan jaminan aset kripto')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    <div class="p-5 rounded-3xl bg-amber-500/5 border border-amber-500/20 text-xs text-slate-300 leading-relaxed">
        <strong class="text-white block mb-1">Cara kerja HODL-to-Drive</strong>
        Kripto kamu dikunci sebagai jaminan (LTV maksimal {{ (int) (\Modules\Finance\Domain\Models\Loan::MAX_LTV_AT_OPEN * 100) }}%),
        pinjaman dicairkan untuk membeli mobil, dan kamu mencicil tiap bulan. Aset tetap milikmu — kecuali LTV menembus
        {{ (int) (\Modules\Finance\Domain\Models\Loan::LIQUIDATION_LTV * 100) }}% yang memicu likuidasi otomatis.
    </div>

    @forelse($loans as $loan)
    @php
        $price = $prices[$loan->collateralAsset->symbol] ?? null;
        $ltv = $price ? $loan->currentLtv($price) : 0;
        $ltvColor = $ltv >= \Modules\Finance\Domain\Models\Loan::LIQUIDATION_LTV
            ? 'text-rose-400'
            : ($ltv >= \Modules\Finance\Domain\Models\Loan::MARGIN_CALL_LTV ? 'text-amber-400' : 'text-emerald-400');
    @endphp
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-white">
                    Pembiayaan {{ $loan->formatted_principal }} · {{ $loan->tenor_months }} bulan
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Jaminan {{ $loan->collateral_qty }} {{ $loan->collateralAsset->symbol }} ·
                    dibuka {{ $loan->opened_at?->format('d M Y') }}
                </p>
            </div>
            <span class="px-3 py-1 rounded-full text-[11px] font-bold border {{ $loan->status->badgeClasses() }}">
                {{ $loan->status->label() }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
                <span class="text-slate-400 block mb-1">Sisa Pokok</span>
                <span class="font-mono font-bold text-white">{{ $loan->formatted_outstanding }}</span>
            </div>
            <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
                <span class="text-slate-400 block mb-1">LTV Saat Ini</span>
                <span class="font-mono font-bold {{ $ltvColor }}">{{ number_format($ltv * 100, 1) }}%</span>
            </div>
            <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
                <span class="text-slate-400 block mb-1">Cicilan Terbayar</span>
                <span class="font-mono font-bold text-white">{{ $loan->paidInstallmentsCount() }} / {{ $loan->tenor_months }}</span>
            </div>
            <div class="bg-slate-900/60 rounded-2xl border border-slate-700/60 p-4">
                <span class="text-slate-400 block mb-1">Cicilan Berikutnya</span>
                <span class="font-mono font-bold text-white">
                    {{ $loan->nextUnpaidInstallment()?->formatted_amount ?? '—' }}
                </span>
            </div>
        </div>

        <a href="{{ route('finance.loans.show', $loan) }}"
            class="inline-flex px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition-all">
            Kelola Pembiayaan
        </a>
    </div>
    @empty
    <div class="py-20 text-center space-y-3">
        <p class="text-slate-400 text-sm">Kamu belum punya pembiayaan aktif.</p>
        <a href="{{ route('store.catalog.index') }}?type=cars" class="text-amber-400 text-sm font-semibold hover:underline">
            Lihat unit mobil yang bisa dicicil &rarr;
        </a>
    </div>
    @endforelse
</div>
@endsection
