@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analisis Rasio Klaim Asuransi Kesehatan (Loss Ratio)</h1>
            <p class="text-sm text-gray-600">Monitoring kesehatan finansial portofolio asuransi kesehatan korporasi & individu</p>
        </div>
        <a href="{{ route('insurance.health.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Daftar Polis</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Loss Ratio Terkini</span>
            <p class="text-2xl font-bold {{ ($report['is_healthy_ratio'] ?? true) ? 'text-emerald-600' : 'text-rose-600' }} mt-2">
                {{ $report['loss_ratio_percent'] ?? 0 }}%
            </p>
            <span class="text-xs text-gray-400">Ambang sehat batas regulator: ≤ 75%</span>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Premi Diperoleh (Earned)</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format(($report['total_earned_premium_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Klaim Terjadi (Incurred)</span>
            <p class="text-2xl font-bold text-rose-600 mt-2">Rp {{ number_format(($report['incurred_claims_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Klaim Dalam Proses</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">Rp {{ number_format(($report['pending_claims_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Breakdown Klaim Berdasarkan Jenis Perawatan</h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Rawat Inap (Inpatient / Surgical)</span>
                    <span class="text-sm font-semibold text-gray-900">Rp {{ number_format($report['breakdown_by_treatment']['inpatient_idr'] ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-sm text-gray-600">Rawat Jalan (Outpatient / Specialist)</span>
                    <span class="text-sm font-semibold text-gray-900">Rp {{ number_format($report['breakdown_by_treatment']['outpatient_idr'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Status Pemrosesan Klaim</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-emerald-50 rounded-lg">
                    <span class="text-xs text-emerald-800 uppercase font-semibold">Disetujui / Selesai</span>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $report['claim_count_status']['approved'] ?? 0 }}</p>
                </div>
                <div class="p-4 bg-amber-50 rounded-lg">
                    <span class="text-xs text-amber-800 uppercase font-semibold">Menunggu Review</span>
                    <p class="text-2xl font-bold text-amber-700 mt-1">{{ $report['claim_count_status']['pending'] ?? 0 }}</p>
                </div>
                <div class="p-4 bg-rose-50 rounded-lg">
                    <span class="text-xs text-rose-800 uppercase font-semibold">Ditolak</span>
                    <p class="text-2xl font-bold text-rose-700 mt-1">{{ $report['claim_count_status']['rejected'] ?? 0 }}</p>
                </div>
                <div class="p-4 bg-indigo-50 rounded-lg">
                    <span class="text-xs text-indigo-800 uppercase font-semibold">Banding Medis</span>
                    <p class="text-2xl font-bold text-indigo-700 mt-1">{{ $report['claim_count_status']['appealed'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

