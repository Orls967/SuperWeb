<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Transaksi Dompet Syariah 30 Lini</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Implementasi Kontrak G7-K7 SyariahWalletPayment & Validasi Halal Gate</p>
            </div>
            <a href="{{ route('syariah.ops.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Command Center</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Transaksi</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ref Pesanan</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Lini Bisnis</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Merchant</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Nominal Kotor</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Ujrah Admin</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Net Merchant</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($transactions as $tx)
                        <tr>
                            <td class="px-6 py-4 font-mono font-medium">{{ $tx->transaction_ref }}</td>
                            <td class="px-6 py-4 font-mono">{{ $tx->order_reference }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 dark:bg-gray-700">{{ $tx->business_line_code }}</span>
                            </td>
                            <td class="px-6 py-4">{{ $tx->merchant_code }}</td>
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">Rp {{ number_format($tx->gross_amount_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-emerald-600">Rp {{ number_format($tx->admin_fee_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-semibold">Rp {{ number_format($tx->net_merchant_amount_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-semibold {{ $tx->status === 'SUCCESS' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $tx->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">Belum ada transaksi dompet tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</x-app-layout>
