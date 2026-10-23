@extends('layouts.app')

@section('title', 'Incoterms & Landed Cost Breakdown')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Incoterms 2020 & Landed Cost</h1>
            <p class="text-sm text-gray-500">Kalkulasi biaya mendarat impor: CIF, Bea Masuk (BM), PPN Impor 11%, dan PPh 22 Impor.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Fase 49 Trade
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Nilai CIF IDR</span>
            <div class="mt-2 text-2xl font-bold text-gray-900">Rp 1.000.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Bea Masuk (BM 5%)</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">Rp 50.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">PPN Impor (11%)</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">Rp 115.500.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Landed Cost</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 1.191.750.000</div>
        </div>
    </div>
</div>
@endsection
