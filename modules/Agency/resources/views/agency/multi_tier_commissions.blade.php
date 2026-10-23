@extends('layouts.app')

@section('title', 'Multi-Tier Agent Commissions')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Multi-Tier Agent Commissions</h1>
            <p class="text-sm text-gray-500">Kalkulasi komisi agen langsung dan komisi override upline/master agent berjenjang.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Fase 45 Agency
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Komisi Langsung (Direct)</h3>
            <p class="text-3xl font-bold text-indigo-600">Rp 184.500.000</p>
            <p class="text-xs text-gray-500 mt-1">Tier rata-rata 8.0%</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Upline Override Tier 2</h3>
            <p class="text-3xl font-bold text-emerald-600">Rp 46.125.000</p>
            <p class="text-xs text-gray-500 mt-1">Override rata-rata 2.0%</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Agen Aktif</h3>
            <p class="text-3xl font-bold text-gray-900">318</p>
            <p class="text-xs text-gray-500 mt-1">Dalam 28 wilayah jaringan</p>
        </div>
    </div>
</div>
@endsection
