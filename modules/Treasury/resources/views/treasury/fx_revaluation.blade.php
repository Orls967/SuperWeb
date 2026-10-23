@extends('layouts.app')

@section('title', 'Multi-Currency FX Revaluation')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Multi-Currency FX Revaluation</h1>
            <p class="text-sm text-gray-500">Revaluasi saldo akun kas dan piutang/utang valas bulanan (PSAK 10 / IAS 21).</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Fase 48 Treasury FX
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Total Saldo Valas (USD Eq)</h3>
            <p class="text-3xl font-bold text-gray-900">$4,850,000</p>
            <p class="text-xs text-gray-500 mt-1">USD, EUR, SGD, JPY, CNY</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Kurs Spot JISDOR (USD/IDR)</h3>
            <p class="text-3xl font-bold text-indigo-600">Rp 15.650</p>
            <p class="text-xs text-gray-500 mt-1">Bank Indonesia closing rate</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Unrealized FX Gain/(Loss)</h3>
            <p class="text-3xl font-bold text-emerald-600">+Rp 420.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Terposting otomatis ke buku besar</p>
        </div>
    </div>
</div>
@endsection
