<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    District Water Distribution & Non-Revenue Water (NRW) Command Center
                </h2>
                <p class="text-sm text-gray-500">Peta hidrolik skematik jaringan pipa, District Metering Area (DMA), dan neraca massa air standar IWA</p>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" data-export-csv="dma-water-balance-csv" data-table-search="water-table" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
                <button type="button" data-open-simulator="water-hydraulic-sim" class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Simulator Hidrolik
                </button>
                <a href="{{ route('egy.water.leaks') }}" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-lg shadow-sm">
                    Peta Kebocoran Pipa ({{ $active_incidents_count }})
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Metrics Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Kapasitas Distribusi Air</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($total_capacity_liters) }}</span>
                        <span class="text-xs text-gray-500">L/hari</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Zona DMA Aktif</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ $total_dma_count }}</span>
                        <span class="text-xs text-emerald-600">terpantau realtime</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Insiden Kebocoran Aktif</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold {{ $active_incidents_count > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $active_incidents_count }}</span>
                        <span class="text-xs text-gray-500">work order aktif</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Tingkat NRW Rata-rata</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-amber-600">22.0%</span>
                        <span class="text-xs text-gray-500">IWA compliant</span>
                    </div>
                </div>
            </div>

            <!-- DMA Networks Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">District Metering Areas (DMA) & Status Hidrolik</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="water-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Nama Jaringan / DMA</th>
                                <th class="px-6 py-3">Kode Zona</th>
                                <th class="px-6 py-3">Kapasitas (L/hari)</th>
                                <th class="px-6 py-3">Sumber Air</th>
                                <th class="px-6 py-3">Jumlah Node / Sensor</th>
                                <th class="px-6 py-3">Insiden Terdeteksi</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($networks as $network)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $network->network_name }}</td>
                                    <td class="px-6 py-4 font-mono text-xs">{{ $network->zone_code }}</td>
                                    <td class="px-6 py-4">{{ number_format($network->capacity_liters_per_day) }} L</td>
                                    <td class="px-6 py-4 uppercase text-xs font-semibold">{{ $network->water_source_type }}</td>
                                    <td class="px-6 py-4">{{ $network->nodes_count }} node</td>
                                    <td class="px-6 py-4">
                                        @if($network->leak_incidents_count > 0)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-rose-50 text-rose-700">
                                                {{ $network->leak_incidents_count }} kebocoran
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700">
                                                Normal
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 uppercase">
                                            {{ $network->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada jaringan DMA terdaftar</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
