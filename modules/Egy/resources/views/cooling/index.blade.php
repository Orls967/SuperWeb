<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Central District Cooling Plant & Chiller COP Operations
                </h2>
                <p class="text-sm text-gray-500">Pemantauan sentral pendingin distrik, efisiensi koefisien performa (COP), dan telemetri BTU meter</p>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" data-export-csv="cooling-telemetry-csv" data-table-search="cooling-table" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
                <button type="button" data-open-simulator="chiller-staging-sim" class="px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Simulator Staging Chiller
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Kapasitas Pendingin</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($total_refrigeration_tons) }}</span>
                        <span class="text-xs text-gray-500">Refrigeration Tons (RT)</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Rata-rata Koefisien Performa (COP)</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-emerald-600">{{ number_format($average_cop_bps / 100, 2) }}</span>
                        <span class="text-xs text-gray-500">efisiensi tinggi (target > 4.5)</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Perbandingan AC Individual</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-indigo-600">+40% Hemat</span>
                        <span class="text-xs text-gray-500">vs COP 3.0 split AC</span>
                    </div>
                </div>
            </div>

            <!-- Telemetry Readings -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Telemetri BTU Meter & Operasional Chiller Sentral</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="cooling-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Waktu Rekam</th>
                                <th class="px-6 py-3">Plant Pendingin</th>
                                <th class="px-6 py-3">Unit Chiller</th>
                                <th class="px-6 py-3">Delta Suhu (°C)</th>
                                <th class="px-6 py-3">Produksi Termal (kWh_th)</th>
                                <th class="px-6 py-3">Listrik Input (kWh)</th>
                                <th class="px-6 py-3">COP Aktual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recent_readings as $r)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-mono">{{ $r->recorded_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $r->plant?->plant_name }}</td>
                                    <td class="px-6 py-4 font-mono text-xs">{{ $r->chiller?->chiller_number }}</td>
                                    <td class="px-6 py-4">{{ number_format($r->delta_temp_c_milli / 1000, 1) }} °C</td>
                                    <td class="px-6 py-4 font-bold text-blue-600">{{ number_format($r->thermal_kwh_th) }}</td>
                                    <td class="px-6 py-4 font-mono">{{ number_format($r->electric_kwh) }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded {{ $r->cop_bps >= 450 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            COP {{ number_format($r->cop_bps / 100, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada data telemetri BTU pendingin distrik</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
