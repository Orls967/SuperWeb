@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pasar Energi P2P Antar-Prosumer Microgrid</h1>
            <p class="text-sm text-gray-600">Perdagangan daya listrik terdistribusi lokal antara penghasil solar rooftop dan konsumen kawasan.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-amber-500">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Tarif Sewa Jaringan (Wheeling)</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">5.00% (500 bps)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-emerald-600">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Penyelesaian Ledger</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">Otomatis & Real-time</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-600">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Koneksi Smart Meter</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">Interval 15 Menit</div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Daftar Microgrid Siap Perdagangan P2P</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Microgrid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kawasan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kapasitas (kW)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode Jaringan</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($microgrids as $mg)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm font-semibold text-blue-600">{{ $mg->microgrid_code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $mg->site_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($mg->total_capacity_kw) }} kW</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ strtoupper($mg->operating_mode) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada microgrid terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
