@extends('layouts.app')

@section('title', 'IP Territory Licensing & Patents')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">IP Territory Licensing</h1>
            <p class="text-sm text-gray-500">Tata kelola lisensi paten, merek dagang, dan kepatuhan wilayah distribusi mitra.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
            IP Governance
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Lisensi Paten Aktif</h3>
            <p class="text-3xl font-bold text-indigo-600">14</p>
            <p class="text-xs text-gray-500 mt-1">Terdaftar dan terlindungi</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Masa Berlaku &lt; 60 Hari</h3>
            <p class="text-3xl font-bold text-amber-600">2</p>
            <p class="text-xs text-gray-500 mt-1">Perlu perpanjangan segera</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Lisensi Kedaluwarsa</h3>
            <p class="text-3xl font-bold text-emerald-600">0</p>
            <p class="text-xs text-gray-500 mt-1">100% Kepatuhan hukum</p>
        </div>
    </div>
</div>
@endsection
