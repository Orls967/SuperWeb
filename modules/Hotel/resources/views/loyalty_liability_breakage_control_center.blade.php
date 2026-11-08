@extends('layouts.app')

@section('title', 'Loyalty Liability & Breakage Control Center — IFRS 15')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-emerald-200 dark:border-emerald-800">
                        IFRS 15 / PSAK 72 Kepatuhan Keuangan
                    </span>
                    <span class="text-xs text-slate-500">Masa Berlaku 730 Hari & Sweeping Idempoten</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Pusat Kendali Liabilitas Poin & Pengakuan Breakage
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Pemantauan alokasi FIFO batch poin, penuaan kedaluwarsa, dan pembukuan pendapatan resmi atas poin hangus.
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
        <!-- Waterfall Component -->
        <x-hotel::breakage_waterfall_chart
            :totalEarnedPts="1000000"
            :totalRedeemedPts="650000"
            :totalExpiredPts="100000"
            :activeOutstandingPts="250000"
            :breakageRevenueIdr="10000000" />

        <!-- Timeline Component -->
        <x-hotel::points_expiry_fifo_timeline :batches="$batches" />

        <!-- Audit Execution Panel -->
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-2xl p-6 text-white border border-indigo-900 shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-1">
                <h4 class="text-base font-bold text-slate-100">Eksekusi Penutupan Buku Breakage Harian (Night Audit Sweeper)</h4>
                <p class="text-xs text-indigo-300 max-w-2xl">
                    Perintah otomatis ini akan memindai seluruh batch poin yang telah melewati masa berlaku 730 hari, memotong liabilitas pada neraca keuangan, dan membukukan pendapatan resmi ke akun <code class="text-amber-300">htl:loyalty_breakage_revenue:IDR</code>.
                </p>
            </div>
            <button type="button" class="px-5 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs transition shadow-lg tracking-wide uppercase whitespace-nowrap">
                Jalankan Sweeper Breakage
            </button>
        </div>
    </div>
</div>
@endsection

