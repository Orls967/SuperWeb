<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftar Transaksi Antar-Perusahaan (Intercompany Transactions)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Transaksi Cermin (Mirror Transactions)</h3>
                        <p class="text-xs text-gray-500 mt-1">Seluruh mutasi diposting ke ledger dengan aturan tanda tunggal (Debit -, Kredit +) dan netto seimbang.</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">
                            State Machine 8-Status
                        </span>
                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">
                            Single-Sign Ledger
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Tx</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entitas Penjual (AR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entitas Pembeli (AP)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nominal (IDR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($transactions as $tx)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $tx->transaction_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $tx->selling_entity }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $tx->buying_entity }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $tx->description }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-semibold text-sm text-gray-900">Rp {{ number_format($tx->amount_idr) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">
                                            {{ strtoupper($tx->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada transaksi antar-perusahaan tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
