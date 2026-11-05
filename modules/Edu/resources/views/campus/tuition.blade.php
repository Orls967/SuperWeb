<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tagihan Biaya Kuliah (UKT)') }}
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">No Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Semester</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tagihan Kotor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Beasiswa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Bayar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($invoices as $inv)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">{{ $inv->invoice_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $inv->term->name ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">Rp{{ number_format($inv->gross_amount_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-green-600">-Rp{{ number_format($inv->scholarship_deduction_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-bold">Rp{{ number_format($inv->net_payable_idr, 0, ',', '.') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ strtoupper($inv->status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada tagihan UKT.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

