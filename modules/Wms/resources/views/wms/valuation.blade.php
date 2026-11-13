<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Inventory Valuation &amp; Ledger Reconciliation</h2>
            <a href="{{ route('wms.export.valuation') }}" class="text-sm font-medium text-indigo-600 hover:underline">Export CSV</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Rekonsiliasi Subledger vs Buku Besar (wms:inventory:*:IDR)">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-600">
                                <th class="py-2 pr-4">Gudang</th>
                                <th class="py-2 pr-4">Akun Ledger</th>
                                <th class="py-2 pr-4">Subledger (IDR)</th>
                                <th class="py-2 pr-4">Buku Besar (IDR)</th>
                                <th class="py-2">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($warehouses as $wh)
                                <tr>
                                    <td class="py-2 pr-4 font-medium">{{ $wh['warehouse_code'] }}</td>
                                    <td class="py-2 pr-4 font-mono text-xs">{{ $wh['account_code'] }}</td>
                                    <td class="py-2 pr-4">{{ number_format($wh['subledger_value_idr']) }}</td>
                                    <td class="py-2 pr-4">{{ number_format($wh['ledger_balance_idr']) }}</td>
                                    <td class="py-2 font-semibold {{ $wh['discrepancy_idr'] === 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ number_format($wh['discrepancy_idr']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">Belum ada data gudang.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>
