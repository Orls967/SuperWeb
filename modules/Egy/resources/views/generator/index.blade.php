@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pusat Kesiapan Genset Darurat & BBM</h1>
            <p class="text-sm text-gray-600">Pencatatan pengujian beban mingguan, jam operasional genset, dan emisi Scope 1 bahan bakar diesel.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Riwayat Pengujian & Operasional Genset</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aset Genset</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi Kawasan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe Uji</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BBM Solar</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emisi Scope 1</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hasil</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm font-semibold text-blue-600">{{ $log->generator_asset_code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $log->site_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ str_replace('_', ' ', $log->run_type) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $log->duration_minutes }} menit</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($log->fuel_consumed_liters) }} L</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($log->emissions_kg_co2e) }} kg CO2e</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $log->is_passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $log->is_passed ? 'LULUS UJI' : 'GAGAL' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada riwayat uji genset tercatat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
