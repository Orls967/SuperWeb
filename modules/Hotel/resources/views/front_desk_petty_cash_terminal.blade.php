@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Terminal Kas Kecil & Kasir Front Desk (Petty Cash)</h1>
            <p class="text-sm text-slate-500">Pencatatan pengeluaran kecil darurat tamu (ongkos parkir, meterai, porter) dan rekonsiliasi kas laci.</p>
        </div>
    </div>

    <!-- Ringkasan Saldo Kas Kecil -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Saldo Kas Awal (Opening Float)</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">Rp 2.000.000</div>
            <p class="text-xs text-slate-500 mt-1">Uang kembalian pecahan kecil</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Total Pengeluaran Kasir Hari Ini</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">Rp 450.000</div>
            <p class="text-xs text-slate-500 mt-1">Didukung kuitansi fisik</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Fisik Kas Tersisa di Brankas</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 1.550.000</div>
            <p class="text-xs text-slate-500 mt-1">Penghitungan fisik akurat</p>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <span class="text-xs font-medium text-slate-400 uppercase">Status Selisih Kas (Variance)</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 0 (COCOK)</div>
            <p class="text-xs text-slate-500 mt-1">Rekonsiliasi lolos seimbang</p>
        </div>
    </div>
</div>
@endsection
