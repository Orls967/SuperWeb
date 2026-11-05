@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Rincian Polis Asuransi Pertanian Parametrik</h1>
            <p class="text-sm text-gray-600">Pemantauan data lahan, komoditas panen, dan parameter pemicu oracle cuaca</p>
        </div>
        <a href="{{ route('insurance.agri.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Daftar Polis</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Nomor Polis Tani</span>
            <p class="text-xl font-bold font-mono text-gray-900 mt-1">{{ $policy->policy_number }}</p>
            <span class="text-xs text-gray-400">Komoditas: {{ $policy->commodity }}</span>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Uang Pertanggungan</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">Rp {{ number_format($policy->sum_insured_idr, 0, ',', '.') }}</p>
            <span class="text-xs text-gray-400">Luas Lahan: {{ $policy->farm_area_ha }} Hektar</span>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Ambang Batas Pemicu</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $policy->trigger_threshold_val }} ({{ $policy->index_type }})</p>
            <span class="text-xs text-gray-400">Status: {{ strtoupper($policy->status) }}</span>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Riwayat Klaim Pada Polis Ini</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nomor Klaim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Indeks Aktual</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gross Claim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Potongan Pinjaman</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pencairan Petani</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($policy->claims as $c)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-gray-900">{{ $c->claim_number }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-amber-600">{{ $c->actual_index_val }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($c->gross_claim_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-indigo-600 font-medium">- Rp {{ number_format($c->loan_offset_amount_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-emerald-600 font-bold">Rp {{ number_format($c->net_farmer_payout_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">{{ $c->status }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada klaim untuk polis ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

