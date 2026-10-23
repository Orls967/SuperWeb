@extends('layouts.app')

@section('title', 'Bonded Zone Movement Audit')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Bonded Zone Audit & Hash-Chain</h1>
            <p class="text-sm text-gray-500">Audit kepatuhan Kawasan Berikat (KB) dan pelacakan tahapan kontainer logistik ber-hash.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            Kawasan Berikat Audit
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Audit Kepatuhan KB</h3>
            <p class="text-3xl font-bold text-emerald-600">100%</p>
            <p class="text-xs text-gray-500 mt-1">Nol kebocoran fisik barang impor</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">COO Terverifikasi (FTA)</h3>
            <p class="text-3xl font-bold text-indigo-600">38 Form E/D</p>
            <p class="text-xs text-gray-500 mt-1">Tarif preferensi bea masuk 0%</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Penyimpangan Rute (Alert)</h3>
            <p class="text-3xl font-bold text-gray-900">0</p>
            <p class="text-xs text-gray-500 mt-1">Hash-chain konsisten</p>
        </div>
    </div>
</div>
@endsection
