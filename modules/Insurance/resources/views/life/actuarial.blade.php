@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analisis Aktuaria Asuransi Jiwa & Mortalitas</h1>
            <p class="text-sm text-gray-600">Pemantauan portofolio liabilitas aktuaria berbasis Tabel Mortalitas Indonesia (TMI IV)</p>
        </div>
        <a href="{{ route('insurance.life.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Daftar Polis</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Polis Jiwa Aktif</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $report['active_policies_count'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Uang Pertanggungan</span>
            <p class="text-2xl font-bold text-blue-600 mt-2">Rp {{ number_format(($report['total_sum_insured_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Rerata Uang Pertanggungan</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format(($report['average_sum_insured_idr'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Eksposur Perokok</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">{{ $report['lifestyle_risk']['smoker_percentage'] ?? 0 }}%</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Kelompok Usia Tertanggung</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kelompok Usia</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah Tertanggung</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Eksposur (IDR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($report['age_distribution'] ?? []) as $bracket => $data)
                <tr>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $bracket }} Tahun</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $data['count'] }} Peserta</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($data['exposure_idr'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

