<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pinjaman Intercompany & Amortisasi (Intercompany Loans)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Portofolio Pembiayaan Intra-Grup</h3>
                        <p class="text-xs text-gray-500 mt-1">Bunga berbasis arm's length (BI Rate / SOFR + spread) dengan pemotongan pajak PPh 23 / 26 otomatis.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-indigo-100 text-indigo-800">
                        Algoritma Bersertifikat D6
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pinjaman</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kreditur (Lender)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Debitur (Borrower)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pokok (IDR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Suku Bunga</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($loans as $loan)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $loan->loan_number }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $loan->lender_entity }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $loan->borrower_entity }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($loan->principal_idr) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 font-mono">
                                        {{ number_format(($loan->base_rate_bps + $loan->spread_bps) / 100, 2) }}%
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $loan->due_date ? $loan->due_date->format('Y-m-d') : '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">
                                            {{ strtoupper($loan->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada pinjaman intercompany tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

