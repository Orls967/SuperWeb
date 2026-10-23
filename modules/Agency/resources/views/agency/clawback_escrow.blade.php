@extends('layouts.app')

@section('title', 'Commission Hold & Clawback Escrow')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Commission Hold & Clawback Escrow</h1>
            <p class="text-sm text-gray-500">Masa penahanan komisi selama periode retur barang pelanggan dan proteksi clawback.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
            Escrow Protection
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Komisi Tertahan (Hold)</h3>
            <p class="text-3xl font-bold text-amber-600">Rp 54.200.000</p>
            <p class="text-xs text-gray-500 mt-1">Dalam masa proteksi retur 14 hari</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Siap Dicairkan (Payable)</h3>
            <p class="text-3xl font-bold text-emerald-600">Rp 128.400.000</p>
            <p class="text-xs text-gray-500 mt-1">Masa hold kedaluwarsa tanpa klaim</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Clawback Dieksekusi</h3>
            <p class="text-3xl font-bold text-rose-600">Rp 3.800.000</p>
            <p class="text-xs text-gray-500 mt-1">Retur barang oleh end-customer</p>
        </div>
    </div>
</div>
@endsection
