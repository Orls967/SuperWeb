@extends('layouts.app')

@section('title', 'Price Waterfall & Margin Guardrail Simulator')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Price Waterfall Simulator</h1>
            <p class="text-sm text-gray-500">Simulasi stacking diskon promo, volume, cash rebate, dan pembatasan margin floor otomatis.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Fase 44 Pricing
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">List Price Rata-rata</span>
            <div class="mt-2 text-2xl font-bold text-gray-900">Rp 120.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Diskon Bertingkat</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">-9.8%</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Pocket Price Bersih</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">Rp 108.240</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Gross Margin Terjaga</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">32.4%</div>
        </div>
    </div>
</div>
@endsection
