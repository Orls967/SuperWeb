<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Perjanjian Sewa Kapal (Charter Party) & Perhitungan Laytime') }}
            </h2>
            <a href="{{ route('port.fleet.index') }}" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Kembali ke Armada</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tabel Charter Parties -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Fixture Perjanjian Sewa (Gencon / NYPE)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">No. Fixture</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Kapal</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Penyewa (Charterer)</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Tipe / Form</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Tarif Sewa (USD)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Demurrage/Hari</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($charterParties as $party)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-blue-600">{{ $party->fixture_number }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $party->vessel->vessel_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-900 font-medium">{{ $party->charterer_name }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ str_replace('_', ' ', $party->charter_type) }} ({{ $party->charter_form }})</td>
                                    <td class="px-4 py-3 text-right font-mono font-medium">${{ number_format($party->freight_or_hire_rate, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600">${{ number_format($party->demurrage_rate_daily, 2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-gray-100 text-gray-800">
                                            {{ $party->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada fixture perjanjian sewa kapal.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Tagihan Demurrage Belum Terbayar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tagihan Demurrage Menunggu Pembayaran</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">No. Tagihan</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pihak Tertagih</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Nominal (USD)</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Tanggal Terbit</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Jatuh Tempo</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($unpaidInvoices as $inv)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-red-600">{{ $inv->invoice_number }}</td>
                                    <td class="px-4 py-3 text-gray-800">{{ $inv->bill_to_party }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">${{ number_format($inv->amount_usd, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $inv->invoice_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-red-500 font-medium">{{ $inv->due_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 font-bold">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">Tidak ada tagihan demurrage yang tertunda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

