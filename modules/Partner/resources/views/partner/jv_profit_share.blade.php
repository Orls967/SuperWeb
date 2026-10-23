@extends('layouts.app')

@section('title', 'JV Equity & Revenue Profit Sharing')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">JV Profit Sharing & Equity</h1>
            <p class="text-sm text-gray-500">Perhitungan pembagian laba usaha bersama (Joint Venture) dan hak royalti ekuitas.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Fase 47 Partner JV
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Total JV Revenue Basis</h3>
            <p class="text-3xl font-bold text-gray-900">Rp 12.800.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Periode berjalan konsolidasi</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Biaya Dapat Dikurangkan</h3>
            <p class="text-3xl font-bold text-rose-600">Rp 4.200.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Deductible operating expenses</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Laba Bersih Dibagi (Share)</h3>
            <p class="text-3xl font-bold text-indigo-600">Rp 2.580.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Sesuai rasio kepemilikan JV</p>
        </div>
    </div>
</div>
@endsection
