<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Kontrak ESCO: {{ $contract->contract_number }} ({{ $contract->facility_name }})
                </h2>
                <p class="text-sm text-gray-500">Analisis kelayakan retrofit, baseline disesuaikan cuaca, dan riwayat penyelesaian split savings</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('egy.esco.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">
                    Kembali ke Portofolio ESCO
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Contract Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Baseline Historis</span>
                    <div class="mt-2 text-xl font-bold text-gray-900">{{ number_format($contract->baseline_monthly_kwh) }} kWh</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Tarif Acuan Kontrak</span>
                    <div class="mt-2 text-xl font-bold text-gray-900">Rp {{ number_format($contract->baseline_tariff_idr) }}/kWh</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Rasio Bagi Hasil ESCO</span>
                    <div class="mt-2 text-xl font-bold text-emerald-600">{{ number_format($contract->esco_share_bps / 100, 1) }}%</div>
                </div>
                <div class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Status Kontrak</span>
                    <div class="mt-2 text-xl font-bold uppercase text-indigo-600">{{ $contract->status }}</div>
                </div>
            </div>

            <!-- Settlement History Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Riwayat Penghematan Terverifikasi & Settlement Finansial</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="esco-settlements-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Bulan Periode</th>
                                <th class="px-6 py-3">Baseline Adjusted</th>
                                <th class="px-6 py-3">Aktual (kWh)</th>
                                <th class="px-6 py-3">Hemat Bruto (kWh)</th>
                                <th class="px-6 py-3">Total Hemat (Rp)</th>
                                <th class="px-6 py-3">ESCO Share (Rp)</th>
                                <th class="px-6 py-3">Klien Share (Rp)</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($contract->settlements as $s)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-semibold">{{ $s->period_month }}</td>
                                    <td class="px-6 py-4 font-mono">{{ number_format($s->adjusted_baseline_kwh) }}</td>
                                    <td class="px-6 py-4 font-mono">{{ number_format($s->actual_kwh) }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600 font-mono">{{ number_format($s->gross_savings_kwh) }}</td>
                                    <td class="px-6 py-4 font-bold text-gray-900">Rp {{ number_format($s->total_savings_idr) }}</td>
                                    <td class="px-6 py-4 font-bold text-emerald-600 font-mono">Rp {{ number_format($s->esco_share_idr) }}</td>
                                    <td class="px-6 py-4 font-mono">Rp {{ number_format($s->client_share_idr) }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 uppercase">
                                            {{ $s->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-400">Belum ada data settlement M&V untuk kontrak ini</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
