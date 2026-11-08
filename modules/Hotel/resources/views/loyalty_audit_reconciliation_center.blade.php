@extends('layouts.app')

@section('title', 'Loyalty Audit & Reconciliation Center — Travel Pass')

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 py-8 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div>
                <div class="flex items-center space-x-3 mb-1">
                    <span class="px-3 py-1 bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 font-extrabold text-xs rounded-full uppercase tracking-wider border border-rose-200 dark:border-rose-800">
                        Audit Kriptografis Invarian
                    </span>
                    <span class="text-xs text-slate-500">Zero Discrepancy Toleransi Rp 0</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    Pusat Audit Rekonsiliasi & Integritas Finansial Poin
                </h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                    Verifikasi kepatuhan buku besar perbankan terhadap total saldo batch basis data secara real-time.
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
        <!-- Audit Summary Card -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Status Uji Silang Buku Besar (Cross-Verification Status)</h3>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border">
                    <span class="text-slate-400 block text-[10px] uppercase">Total Poin Aktif Database</span>
                    <span class="text-lg font-black text-slate-900 dark:text-white">1.250.000 PTS</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border">
                    <span class="text-slate-400 block text-[10px] uppercase">Liabilitas Teoritis (x Rp 100)</span>
                    <span class="text-lg font-black text-indigo-600">Rp 125.000.000</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border">
                    <span class="text-slate-400 block text-[10px] uppercase">Saldo Buku Besar Riil</span>
                    <span class="text-lg font-black text-slate-900 dark:text-white">Rp 125.000.000</span>
                </div>
                <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800">
                    <span class="text-emerald-700 dark:text-emerald-300 block text-[10px] uppercase font-bold">Selisih Rekonsiliasi (Delta)</span>
                    <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">Rp 0 (CLEAN)</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

