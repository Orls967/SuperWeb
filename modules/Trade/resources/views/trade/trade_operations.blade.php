@extends('layouts.app')

@section('title', 'International Trade Operations Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Trade Operations Command Center</h1>
            <p class="text-sm text-gray-500">Pusat kendali ekspor-impor, manajemen pelabuhan, Incoterms, dan perizinan lartas.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Global Trade
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Nilai Ekspor</span>
            <div class="mt-2 text-2xl font-bold text-gray-900">$8,450,000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Nilai Impor CIF</span>
            <div class="mt-2 text-2xl font-bold text-indigo-600">$5,120,000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Total Landed Cost Terbayar</span>
            <div class="mt-2 text-2xl font-bold text-emerald-600">Rp 82.400.000.000</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 uppercase">Negara Mitra Dagang</span>
            <div class="mt-2 text-2xl font-bold text-amber-600">18 Negara</div>
        </div>
    </div>
</div>
@endsection
