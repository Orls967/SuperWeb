@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">Restorasi Hutan & Kredit Karbon</h1>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Restorasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Luas Tanam (Ha)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tingkat Kelangsungan Hidup</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Skor NDVI</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kredit Karbon (kg CO2e)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($restorations as $rest)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $rest->restoration_code }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rest->planted_hectares }} Ha</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rest->survival_rate_pct }}%</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $rest->satellite_ndvi_score }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">{{ number_format($rest->carbon_credits_issued) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
