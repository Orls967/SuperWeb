@extends('layouts.app')

@section('title', 'Corporate Treasury & Liquidity Control')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Corporate Treasury Control Tower</h1>
            <p class="text-sm text-gray-500">Monitoring posisi likuiditas terintegrasi, risiko pasar, peramalan kas 90-hari, dan kepatuhan perbankan.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Treasury Core
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Cash & Equivalents</span>
            <div class="mt-2 text-2xl font-bold text-gray-900">Rp 128.450.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Available Credit Lines</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">Rp 75.000.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">90-Day Net Cash Flow</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">+Rp 14.200.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Hedge Coverage Ratio</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">88.5%</div>
        </div>
    </div>
</div>
@endsection
