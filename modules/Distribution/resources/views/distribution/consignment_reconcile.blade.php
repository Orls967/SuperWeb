@extends('layouts.app')

@section('title', 'Consignment Stock Reconciliation')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Consignment Stock Reconciliation</h1>
            <p class="text-sm text-gray-500">Opname fisik stok konsinyasi di gudang distributor dan penagihan penjualan unbilled otomatis.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Fase 43 Consignment
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Ringkasan Rekonsiliasi Terakhir</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Total Titipan Konsinyasi</span>
                <p class="text-2xl font-bold text-gray-900 mt-1">12.450 Units</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Penjualan Terkonversi (Unbilled)</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">1.840 Units</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Nilai Terfakturkan</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">Rp 92.000.000</p>
            </div>
        </div>
    </div>
</div>
@endsection
