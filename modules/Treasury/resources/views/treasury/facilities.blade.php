<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bank Credit Facilities & Covenant Monitoring') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Plafon Fasilitas Kredit Bank</h3>
                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">Covenant DER Permil</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode Fasilitas</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bank Sindikasi</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Limit IDR</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Terpakai IDR</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sisa Plafon</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Max DER</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($facilities as $fac)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs font-bold">{{ $fac->facility_code }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $fac->bank_name }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold">{{ number_format($fac->credit_limit_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-red-600">{{ number_format($fac->drawn_amount_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-green-600 font-bold">
                                        {{ number_format(max(0, $fac->credit_limit_idr - $fac->drawn_amount_idr)) }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-xs">
                                        {{ $fac->max_der_permil ? $fac->max_der_permil / 1000 : $fac->max_debt_equity_ratio }}x
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada fasilitas kredit.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

