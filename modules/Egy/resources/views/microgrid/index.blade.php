@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pusat Kendali Microgrid & Ketahanan Daya</h1>
            <p class="text-sm text-gray-600">Pemantauan status pulau mandiri (islanding), frekuensi grid, dan shedding beban kritis.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-600">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Microgrid Aktif</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ $microgrids->total() }} Kawasan</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-emerald-600">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Status Proteksi Frekuensi</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">50.00 Hz (Nominal)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-600">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Kesiapan Black-Start</span>
            <div class="text-2xl font-bold text-gray-900 mt-1">Tersertifikasi E14</div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Daftar Pengendali Microgrid Kawasan</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Microgrid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lokasi Kawasan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode Operasi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kapasitas (kW)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beban Saat Ini (kW)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penyulang Feeder</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($microgrids as $mg)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm font-semibold text-blue-600">{{ $mg->microgrid_code }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $mg->site_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $mg->operating_mode === 'islanded' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                {{ strtoupper($mg->operating_mode) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($mg->total_capacity_kw) }} kW</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ number_format($mg->current_load_kw) }} kW</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $mg->feeders->count() }} jalur beban</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Belum ada pengontrol microgrid terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $microgrids->links() }}
        </div>
    </div>
</div>
@endsection
