<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Waste Management & Waste-to-Energy (WTE) Command Center
                </h2>
                <p class="text-sm text-gray-500">Penerimaan tonase limbah, konversi biogas ke listrik kWh, penagihan tipping fee, dan baku mutu efluen</p>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" data-export-csv="wte-intake-csv" data-table-search="waste-table" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
                <button type="button" data-open-simulator="wte-biogas-sim" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Simulator Biogas
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Limbah Diterima</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ number_format($total_intake_tons) }}</span>
                        <span class="text-xs text-gray-500">Ton</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Pendapatan Tipping Fee</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-emerald-600">Rp {{ number_format($total_tipping_fee_idr) }}</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Proyeksi Produksi Biogas</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-amber-600">{{ number_format($total_projected_biogas_m3) }}</span>
                        <span class="text-xs text-gray-500">m³</span>
                    </div>
                </div>
            </div>

            <!-- Intakes Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Riwayat Penerimaan Tonase & Tipping Fee</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="waste-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Waktu Timbang</th>
                                <th class="px-6 py-3">Nomor Kendaraan</th>
                                <th class="px-6 py-3">Fasilitas Digester</th>
                                <th class="px-6 py-3">Jenis Limbah</th>
                                <th class="px-6 py-3">Tonase</th>
                                <th class="px-6 py-3">Tipping Fee (Rp)</th>
                                <th class="px-6 py-3">Proyeksi Biogas (m³)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recent_intakes as $intake)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-xs font-mono">{{ $intake->recorded_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-6 py-4 font-mono font-semibold">{{ $intake->vehicle_number }}</td>
                                    <td class="px-6 py-4">{{ $intake->facility?->facility_name }}</td>
                                    <td class="px-6 py-4 capitalize text-xs">{{ str_replace('_', ' ', $intake->waste_type) }}</td>
                                    <td class="px-6 py-4 font-bold">{{ number_format($intake->tonnage_kg / 1000, 2) }} T</td>
                                    <td class="px-6 py-4 font-mono text-emerald-600 font-semibold">Rp {{ number_format($intake->total_tipping_fee_idr) }}</td>
                                    <td class="px-6 py-4 font-mono">{{ number_format($intake->projected_biogas_m3) }} m³</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada data penerimaan limbah</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
