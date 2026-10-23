@extends('layouts.app')

@section('title', 'Approved Vendor List (AVL) & Scorecard')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Supplier AVL & Scorecard</h1>
            <p class="text-sm text-gray-500">Evaluasi kualifikasi vendor, sertifikasi, dan skor performa terintegrasi.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Fase 32 AVL Active
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Qualified Vendors</span>
            <div class="mt-2 text-2xl font-bold text-gray-900">{{ $totalVendors ?? 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Average OTD Rate</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">{{ $avgOtdRate ?? 98.2 }}%</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Quality Score</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">{{ $avgQualityScore ?? 99.4 }}%</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Active Risk Flags</span>
            <div class="mt-2 text-2xl font-bold text-rose-600">{{ $activeRiskFlags ?? 0 }}</div>
        </div>
    </div>
</div>
@endsection
