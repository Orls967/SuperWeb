@extends('layouts.app')

@section('title', 'Alliance Partner Netting Settlement View — Travel Pass')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-amber-200 dark:border-amber-800">
                        Kliring Finansial Aliansi Bilateral
                    </span>
                    <span class="text-xs text-slate-500">Rekonsiliasi Bersih Bulanan (Monthly Netting)</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Penyelesaian Finansial Mitra Aliansi (Alliance Netting)
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Pencocokan saldo transaksi pertukaran poin bilateral dan instruksi transfer pembayaran ke maskapai mitra.
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
        <!-- Settlement Table -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Rekapitulasi Netting Penyelesaian Mitra Aliansi (Bulan Berjalan)</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Total nilai kotor dikurangi potongan fee platform 3.5% = Net Hutang yang wajib ditransfer.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4">Nama Mitra</th>
                            <th class="py-3 px-4 text-center">Volume Transaksi</th>
                            <th class="py-3 px-4 text-right">Total Poin</th>
                            <th class="py-3 px-4 text-right">Nilai Bruto (IDR)</th>
                            <th class="py-3 px-4 text-right">Komisi Platform</th>
                            <th class="py-3 px-4 text-right">Net Hutang Mitra</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <x-hotel::alliance_netting_settlement_row
                            partnerCode="GARUDA_AIR"
                            partnerName="Garuda Indonesia Airlines"
                            :transactionCount="320"
                            :totalPointsTransferred="3200000"
                            :grossPayableIdr="320000000"
                            :transferFeeCollectedIdr="11200000"
                            :netPayableToPartnerIdr="308800000"
                            status="PENDING_SETTLEMENT" />

                        <x-hotel::alliance_netting_settlement_row
                            partnerCode="SINGAPORE_AIR"
                            partnerName="Singapore Airlines KrisFlyer"
                            :transactionCount="180"
                            :totalPointsTransferred="2160000"
                            :grossPayableIdr="216000000"
                            :transferFeeCollectedIdr="8640000"
                            :netPayableToPartnerIdr="207360000"
                            status="SETTLED" />

                        <x-hotel::alliance_netting_settlement_row
                            partnerCode="KAI_EXECUTIVE"
                            partnerName="Kereta Api Indonesia Panoramic"
                            :transactionCount="95"
                            :totalPointsTransferred="475000"
                            :grossPayableIdr="47500000"
                            :transferFeeCollectedIdr="1187500"
                            :netPayableToPartnerIdr="46312500"
                            status="PENDING_SETTLEMENT" />
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

