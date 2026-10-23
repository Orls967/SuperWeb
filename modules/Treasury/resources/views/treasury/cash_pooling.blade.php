@extends('layouts.app')

@section('title', 'Notional Cash Pooling & Zero-Balance Sweep')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Notional Cash Pool & Sweeping</h1>
            <p class="text-sm text-gray-500">Sentralisasi likuiditas anak perusahaan ke rekening induk (header) dengan optimasi bunga.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Liquidity Sweeping
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Struktur Pool Rekening</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Header Account Balance</span>
                <p class="text-2xl font-bold text-gray-900 mt-1">Rp 48.500.000.000</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Total Rekening Anak (Sub)</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">16 Rekening</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Volume Sweep Hari Ini</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">Rp 6.240.000.000</p>
            </div>
        </div>
    </div>
</div>
@endsection
