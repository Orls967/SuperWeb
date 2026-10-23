@extends('layouts.app')

@section('title', 'Vendor Site Audit & Compliance')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Vendor Site Audit & Compliance</h1>
            <p class="text-sm text-gray-500">Pemeriksaan fasilitas produksi, sertifikasi ISO/Halal/BPOM, dan audit lapangan.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            Compliance Audit
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Audit Terjadwal</h3>
            <p class="text-3xl font-bold text-indigo-600">8</p>
            <p class="text-xs text-gray-500 mt-1">Audit fasilitas pada kuartal berjalan</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Sertifikat Mendekati Expired</h3>
            <p class="text-3xl font-bold text-amber-600">3</p>
            <p class="text-xs text-gray-500 mt-1">Kedaluwarsa dalam &lt; 30 hari</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Audit Pass Rate</h3>
            <p class="text-3xl font-bold text-emerald-600">94%</p>
            <p class="text-xs text-gray-500 mt-1">Standar kelayakan operasional</p>
        </div>
    </div>
</div>
@endsection
