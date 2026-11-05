@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Insurance Operations Command Center</h1>
            <p class="text-sm text-gray-600">Papan Eksekutif C-Suite Solvabilitas RBC, Loss Ratio & Cadangan Lintas 30 Lini Asuransi</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('insurance.command.regulatory') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Kepatuhan Regulasi & Keluhan</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Gross Written Premium (GWP)</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format($snapshot->gross_written_premium_idr ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Rasio Solvabilitas RBC</span>
            <p class="text-2xl font-bold text-emerald-600 mt-2">{{ number_format(($snapshot->solvency_rbc_bps ?? 24500) / 100, 1) }}%</p>
            <span class="text-xs text-emerald-700">Ambang sehat batas OJK: ≥ 120%</span>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Loss Ratio Konsolidasi</span>
            <p class="text-2xl font-bold text-blue-600 mt-2">{{ number_format(($snapshot->loss_ratio_bps ?? 5500) / 100, 1) }}%</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Cadangan Teknis</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format($snapshot->total_technical_reserve_idr ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Riwayat Snapshot Metrik Keuangan Harian</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Snapshot</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">GWP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net Earned</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Klaim Incurred</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loss Ratio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RBC Solvency</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($recentSnapshots as $s)
                <tr>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $s->snapshot_date?->format('d M Y') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($s->gross_written_premium_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-700">Rp {{ number_format($s->net_earned_premium_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-rose-600">Rp {{ number_format($s->claims_incurred_idr, 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-blue-600">{{ $s->loss_ratio_bps / 100 }}%</td>
                    <td class="px-6 py-4 text-sm font-bold text-emerald-600">{{ $s->solvency_rbc_bps / 100 }}%</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada riwayat snapshot harian.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

