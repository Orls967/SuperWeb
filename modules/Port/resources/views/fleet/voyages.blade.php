<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Pelaksanaan Pelayaran & Pemantauan Noon Reports') }}
            </h2>
            <a href="{{ route('port.fleet.index') }}" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Kembali ke Armada</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tabel Pelayaran -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Pelayaran Kapal (Voyages)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kode Pelayaran</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kapal</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Rute (Asal &rarr; Tujuan)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Jarak (NM)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Bahan Bakar Terpakai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($voyages as $voyage)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-indigo-600">{{ $voyage->voyage_code }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $voyage->vessel->vessel_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $voyage->origin_port }} &rarr; {{ $voyage->destination_port }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($voyage->planned_distance_nm, 1) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            {{ str_replace('_', ' ', $voyage->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">
                                        {{ number_format($voyage->totalBunkerConsumedVlsfo(), 1) }} MT (VLSFO)
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada riwayat pelayaran.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Laporan Tengah Hari (Noon Reports) Terkini -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Laporan Posisi & Cuaca Tengah Hari (Noon Reports)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Tanggal</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kapal</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Koordinat (Lat / Lon)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Kecepatan (Knots)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Cuaca (Beaufort)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Konsumsi 24j</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Sisa ROB (MT)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recentReports as $report)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700">{{ $report->report_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">{{ $report->vessel->vessel_name ?? '-' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ number_format($report->latitude, 4) }}, {{ number_format($report->longitude, 4) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($report->speed_gps_knots, 1) }} kts</td>
                                    <td class="px-4 py-3 text-center">BF {{ $report->wind_force_beaufort }} ({{ $report->wind_direction }})</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">{{ number_format($report->vlsfo_consumed_24h_mt, 1) }} MT</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-600">{{ number_format($report->vlsfo_rob_mt, 1) }} MT</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada noon reports tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

