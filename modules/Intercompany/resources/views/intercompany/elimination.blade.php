<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Jurnal Eliminasi Konsolidasi Keuangan (Consolidation Eliminations)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Ayat Jurnal Eliminasi Bilateral (PSAK 65 / IFRS 10)</h3>
                        <p class="text-xs text-gray-500 mt-1">Mengeliminasi saldo resiprokal piutang-utang, penjualan intra-grup, dan laba persediaan yang belum terealisasi.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-purple-100 text-purple-800">
                        Algoritma Bersertifikat D5
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Eliminasi</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akun Debit</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal Debit</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akun Kredit</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal Kredit</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($eliminations as $e)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $e->elimination_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $e->period }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 font-semibold text-gray-800">{{ $e->elimination_type }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-blue-600">{{ $e->debit_account_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($e->debit_amount_idr) }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-green-600">{{ $e->credit_account_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($e->credit_amount_idr) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada jurnal eliminasi konsolidasi tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
