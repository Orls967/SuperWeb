@extends('layouts.app')

@section('title', 'Customs Declaration (PIB / PEB)')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Customs Declaration (PIB / PEB)</h1>
            <p class="text-sm text-gray-500">Pemberitahuan Impor Barang (PIB) dan Pemberitahuan Ekspor Barang (PEB) terintegrasi DJBC.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Customs Clearance
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Aktivitas Deklarasi Terakhir</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Total PIB Terbit</span>
                <p class="text-2xl font-bold text-gray-900 mt-1">42 Dokumen</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Total PEB Terbit</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">28 Dokumen</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Jalur Hijau Clearance</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">95.2%</p>
            </div>
        </div>
    </div>
</div>
@endsection
