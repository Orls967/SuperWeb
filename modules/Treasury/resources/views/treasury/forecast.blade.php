<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('13-Week Cash Forecast (Seasonal D3)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Proyeksi Arus Kas Mingguan</h3>
                    <span class="px-2 py-1 text-xs rounded bg-indigo-100 text-indigo-800">Dekomposisi Musiman OLS</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Minggu</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mulai</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Inflow</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Outflow</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net Flow</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Saldo Akhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($forecasts as $fc)
                                <tr>
                                    <td class="px-4 py-2 text-center font-bold">W{{ $fc->week_number }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $fc->start_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-green-600 font-semibold">{{ number_format($fc->projected_inflow_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-red-600 font-semibold">{{ number_format($fc->projected_outflow_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-bold {{ $fc->net_cash_flow_idr >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($fc->net_cash_flow_idr) }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm font-extrabold text-gray-900">
                                        {{ number_format($fc->closing_balance_idr) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada proyeksi kas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

