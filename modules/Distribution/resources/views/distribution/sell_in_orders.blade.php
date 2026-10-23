@extends('layouts.app')

@section('title', 'Distributor Sell-In Orders & Allocation')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Distributor Sell-In Orders</h1>
            <p class="text-sm text-gray-500">Pemesanan grosir distributor, validasi limit kredit, dan alokasi stok otomatis.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Fase 43 Distribution
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Total Sell-In Bulan Ini</h3>
            <p class="text-3xl font-bold text-indigo-600">Rp 4.280.000.000</p>
            <p class="text-xs text-gray-500 mt-1">142 Pesanan teralokasi</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Pemanfaatan Limit Kredit</h3>
            <p class="text-3xl font-bold text-amber-600">76.4%</p>
            <p class="text-xs text-gray-500 mt-1">Rata-rata jaringan distributor</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Tingkat Pemenuhan (OTIF)</h3>
            <p class="text-3xl font-bold text-emerald-600">98.1%</p>
            <p class="text-xs text-gray-500 mt-1">Alokasi berbasis prioritas</p>
        </div>
    </div>
</div>
@endsection
