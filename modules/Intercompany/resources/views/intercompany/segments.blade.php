<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Laporan Keuangan Segmen & Kepentingan Non-Pengendali (Segments & NCI)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Kinerja Keuangan Segmen Terkonsolidasi (IFRS 8 / PSAK 65)</h3>
                        <p class="text-xs text-gray-500 mt-1">Distribusi laba bersih antara entitas induk konglomerasi dan kepentingan non-pengendali (NCI).</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-violet-100 text-violet-800">
                        Atribusi Laba Bersih NCI
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entitas Anak</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mata Uang Fungsional</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kepemilikan Induk</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Porsi NCI</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laba Bersih Anak (IDR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hak Laba NCI (IDR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Ekuitas NCI (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($ncis as $nci)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $nci->subsidiary_entity }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 font-semibold">{{ $nci->functional_currency ?? 'IDR' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-sm text-blue-700">{{ number_format($nci->parent_ownership_percent, 1) }}%</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-sm text-purple-700">{{ number_format($nci->nci_ownership_percent, 1) }}%</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($nci->subsidiary_net_income_idr) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-violet-700">Rp {{ number_format($nci->nci_share_idr) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($nci->nci_equity_balance_idr) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada entitas dengan kepemilikan minoritas (NCI) tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

