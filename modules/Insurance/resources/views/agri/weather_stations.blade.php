@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Umpan Stasiun Cuaca Meteorologi & Indeks NDVI Satelit</h1>
            <p class="text-sm text-gray-600">Integrasi sensor IoT curah hujan dan citra satelit vegetasi untuk pemicu otomatis klaim asuransi pertanian</p>
        </div>
        <a href="{{ route('insurance.agri.index') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Polis Tani</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Umpan Real-Time Stasiun Cuaca Regional ({{ $feeds['region_name'] ?? 'Sentra Pertanian' }})</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID Stasiun</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wilayah Distrik</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curah Hujan (30 Hari)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rata-rata Normal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Indeks Vegetasi NDVI</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Risiko Banjir</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Risiko Kekeringan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach(($feeds['stations'] ?? []) as $st)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-indigo-600">{{ $st['station_id'] }}</td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $st['district'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-blue-600">{{ $st['rainfall_last_30d_mm'] }} mm</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-500">{{ $st['normal_average_mm'] }} mm</td>
                    <td class="px-6 py-4 text-sm font-mono text-emerald-600 font-bold">{{ $st['ndvi_vegetation_index'] }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $st['flood_risk_level'] === 'HIGH' ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ $st['flood_risk_level'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $st['drought_risk_level'] === 'SEVERE' ? 'bg-rose-100 text-rose-800' : ($st['drought_risk_level'] === 'MODERATE' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                            {{ $st['drought_risk_level'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

