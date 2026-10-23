@extends('layouts.app')

@section('title', '3-Way Matching & AP Payment Run')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">3-Way Matching & AP Payment Run</h1>
            <p class="text-sm text-gray-500">Rekonsiliasi otomatis PO ↔ GRN ↔ Faktur Tagihan dan eksekusi batch pembayaran AP.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Fase 34 AP Verification
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Automated Match Rate</h3>
            <p class="text-3xl font-bold text-emerald-600">97.8%</p>
            <p class="text-xs text-gray-500 mt-1">Variance &le; 2% tolerance</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Disputed Invoices</h3>
            <p class="text-3xl font-bold text-rose-600">4</p>
            <p class="text-xs text-gray-500 mt-1">Variance flagged for review</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Scheduled AP Run</h3>
            <p class="text-3xl font-bold text-indigo-600">Rp 1.450.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Diskon pelunasan cepat terhitung</p>
        </div>
    </div>
</div>
@endsection
