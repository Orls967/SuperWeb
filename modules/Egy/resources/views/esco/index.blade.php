<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Energy Service Company (ESCO) Performance Contracting
                </h2>
                <p class="text-sm text-gray-500">Penghematan energi terverifikasi IPMVP Option C, kontrak bergaransi, dan penyelesaian bagi hasil penghematan</p>
            </div>
            <div class="flex gap-2 items-center">
                <button type="button" data-export-csv="esco-contracts-csv" data-table-search="esco-table" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Kontrak ESCO Aktif</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-gray-900">{{ count($contracts) }}</span>
                        <span class="text-xs text-gray-500">fasilitas mitra</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Total Nilai Penghematan Energi</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-emerald-600">Rp {{ number_format($total_savings_idr) }}</span>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Bagi Hasil ESCO Provider</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-indigo-600">Rp {{ number_format($total_esco_share_idr) }}</span>
                        <span class="text-xs text-gray-500">70% rasio rata-rata</span>
                    </div>
                </div>
            </div>

            <!-- Contracts Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Portofolio Kontrak ESCO & Verifikasi M&V</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="esco-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Nomor Kontrak</th>
                                <th class="px-6 py-3">Fasilitas / Gedung</th>
                                <th class="px-6 py-3">Klien Party ID</th>
                                <th class="px-6 py-3">Baseline Bulanan</th>
                                <th class="px-6 py-3">Tarif Acuan (Rp)</th>
                                <th class="px-6 py-3">Porsi ESCO (%)</th>
                                <th class="px-6 py-3">Status Kontrak</th>
                                <th class="px-6 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($contracts as $c)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-indigo-600">{{ $c->contract_number }}</td>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $c->facility_name }}</td>
                                    <td class="px-6 py-4 text-xs font-mono">Party #{{ $c->client_party_id }}</td>
                                    <td class="px-6 py-4 font-mono">{{ number_format($c->baseline_monthly_kwh) }} kWh</td>
                                    <td class="px-6 py-4 font-mono">Rp {{ number_format($c->baseline_tariff_idr) }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600">{{ number_format($c->esco_share_bps / 100, 1) }}%</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 uppercase">
                                            {{ $c->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('egy.esco.show', $c->id) }}" class="text-xs text-indigo-600 hover:underline font-semibold">
                                            Detail M&V &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-400">Belum ada kontrak ESCO terdaftar</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
