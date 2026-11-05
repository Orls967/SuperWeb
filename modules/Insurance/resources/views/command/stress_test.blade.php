@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Simulasi Stress-Test Tingkat Solvabilitas Modal (RBC)</h1>
            <p class="text-sm text-gray-600">Ketahanan neraca finansial terhadap guncangan pasar modal, lonjakan klaim katastropik & gagal bayar reasuradur</p>
        </div>
        <a href="{{ route('insurance.command.dashboard') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Command Center</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Gross Written Premium Dasar</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format($simulation['baseline_metrics']['gwp_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Rasio RBC Baseline Saat Ini</span>
            <p class="text-2xl font-bold text-emerald-600 mt-2">{{ $simulation['baseline_metrics']['current_rbc_percent'] ?? 0 }}%</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Ambang Batas Minimum OJK</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $simulation['minimum_regulatory_rbc_percent'] ?? 120 }}%</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Cadangan Teknis Tersedia</span>
            <p class="text-2xl font-bold text-indigo-600 mt-2">Rp {{ number_format($simulation['baseline_metrics']['technical_reserve_idr'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Hasil Proyeksi Berbagai Skenario Guncangan Stres Makroekonomi</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Skenario</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi Guncangan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pertumbuhan Premi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guncangan Klaim</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proyeksi RBC</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status Solvabilitas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($simulation['scenarios'] ?? []) as $key => $sc)
                <tr>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900 font-mono">{{ $key }}</td>
                    <td class="px-6 py-4 text-sm text-gray-700 font-medium">{{ $sc['name'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono {{ $sc['premium_growth_percent'] < 0 ? 'text-rose-600' : 'text-gray-900' }}">{{ $sc['premium_growth_percent'] }}%</td>
                    <td class="px-6 py-4 text-sm font-mono text-amber-600 font-bold">+{{ $sc['claim_shock_percent'] }}%</td>
                    <td class="px-6 py-4 text-sm font-mono font-bold {{ $sc['simulated_rbc_bps'] >= 12000 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $sc['simulated_rbc_bps'] / 100 }}%
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $sc['simulated_rbc_bps'] >= 12000 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $sc['solvency_status'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

