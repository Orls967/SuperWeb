@extends('layouts.app')

@section('title', 'Pusat Rekonsiliasi & Penutupan Piutang City Ledger — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-indigo-50 text-indigo-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pusat Rekonsiliasi & Penutupan Piutang City Ledger (AR Month-End)</h1>
                    <p class="text-sm text-slate-500">Agregasi Sub-Folio Tagihan Korporat, Verifikasi Bukti Kliring Bank & Penyesuaian Saldo Piutang Usaha</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    + Terbitkan Batch Faktur Tagihan Bulanan
                </button>
            </div>
        </div>

        <!-- Escrow / Reconciliation Badge Component -->
        @include('hotel::components.hotel_nightly_escrow_audit_badge')

        <!-- Matriks Aging AR Table Component -->
        @include('hotel::components.city_ledger_aging_table')
    </div>
</div>
@endsection
