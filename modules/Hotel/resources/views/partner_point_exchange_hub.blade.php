@extends('layouts.app')

@section('title', 'Alliance Partner Point Exchange Hub — Global Travel Pass')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-sky-200 dark:border-sky-800">
                        Algoritma H12 · Bilateral Alliance Netting
                    </span>
                    <span class="text-xs text-slate-500">Saga Berkompensasi & Transfer Fee 3.5%</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Hub Pertukaran Poin Mitra Aliansi (Airline & Mobility)
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Konversi instan Poin Hotel PTS menjadi Frequent Flyer Miles maskapai penerbangan internasional dan voucher transportasi darat.
                </p>
            </div>
            <a href="{{ route('hotel.travel_pass.dashboard') }}"
               class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-300 dark:hover:bg-slate-700 transition text-xs flex items-center space-x-1.5 self-start">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Kembali ke Dasbor</span>
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- 3 Partner Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse($partners as $partner)
                <x-hotel::partner_conversion_rate_card
                    :partnerCode="$partner->partner_code"
                    :partnerName="$partner->partner_name"
                    :category="$partner->category"
                    :exchangeRatio="(float) $partner->base_exchange_ratio"
                    :feePercentage="(float) $partner->transfer_fee_percentage"
                    :ptsBalance="45000" />
            @empty
                <div class="col-span-3 text-center py-8 text-xs text-slate-400">
                    Tidak ada mitra aliansi aktif yang terdaftar.
                </div>
            @endforelse
        </div>

        <!-- Recent Transfers Table -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Riwayat Transaksi Transfer Aliansi Terkini</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Jejak audit status saga transfer poin dan verifikasi netting settlement.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4">Waktu Transaksi</th>
                            <th class="py-3 px-4">Mitra Aliansi</th>
                            <th class="py-3 px-4">Akun Mitra Tamu</th>
                            <th class="py-3 px-4 text-right">Poin Didebit</th>
                            <th class="py-3 px-4 text-right">Unit Miles Diterima</th>
                            <th class="py-3 px-4 text-right">Fee Platform (3.5%)</th>
                            <th class="py-3 px-4 text-right">Net Hutang Mitra</th>
                            <th class="py-3 px-4 text-center">Status Transaksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        @forelse($recentTransfers as $log)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 text-slate-500 font-mono">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                    {{ $log->partner_code }}
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-400">
                                    {{ substr($log->member_partner_account, 0, 4) . '****' . substr($log->member_partner_account, -2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ number_format($log->points_debited) }} PTS
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sky-600 dark:text-sky-400 font-bold">
                                    {{ number_format($log->partner_units_credited) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-rose-500">
                                    Rp {{ number_format($log->transfer_fee_idr, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format($log->net_payable_idr, 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider
                                        @if($log->status === 'SUCCESS') bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300
                                        @elseif($log->status === 'COMPENSATED') bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300
                                        @else bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 @endif">
                                        {{ $log->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-400">
                                    Belum ada transaksi transfer aliansi tercatat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

