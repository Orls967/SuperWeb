@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Sistem Baterai Penyimpanan Energi (BESS)</h1>
            <p class="text-sm text-gray-600">Optimalisasi pengisian off-peak, pelepasan on-peak (arbitrase), dan kesehatan sel baterai (SoH).</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Armada Penyimpanan Energi Baterai</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Aset BESS</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi Kawasan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kimia Sel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kapasitas (kWh)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State of Charge (SoC)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State of Health (SoH)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Siklus</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($assets as $bess)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm font-semibold text-blue-600">{{ $bess->bess_code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $bess->site_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $bess->battery_chemistry }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($bess->capacity_kwh) }} kWh</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-emerald-600">{{ $bess->current_soc_bps / 100 }}%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">{{ $bess->state_of_health_bps / 100 }}%</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($bess->cycle_count) }} siklus</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada aset baterai BESS terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $assets->links() }}
        </div>
    </div>
</div>
@endsection
