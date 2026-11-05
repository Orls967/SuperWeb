@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analisis Portofolio & Kecukupan Cadangan Asuransi Mikro</h1>
            <p class="text-sm text-gray-600">Pemantauan volume transaksi harian, eksposur polis mikro aktif & cadangan autopilot</p>
        </div>
        <a href="{{ route('insurance.micro.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Daftar Polis Mikro</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Polis Mikro Aktif</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $analytics['active_policies_count'] ?? 0 }} Polis</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Gross Written Premium Harian</span>
            <p class="text-2xl font-bold text-emerald-600 mt-2">Rp {{ number_format($analytics['total_daily_gwp_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Eksposur Pertanggungan</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format($analytics['total_active_exposure_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Cadangan Wajib Autopilot</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">Rp {{ number_format($analytics['required_autopilot_reserve_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Portofolio Berdasarkan Kategori Produk Mikro</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori Produk</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah Polis Aktif</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Nilai Pertanggungan (IDR)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($analytics['product_breakdown'] ?? []) as $prod => $data)
                <tr>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 font-mono">{{ $prod }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700">{{ $data['count'] }} Peserta</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900 font-bold">Rp {{ number_format($data['exposure_idr'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

