<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Consolidated Multi-Utility Invoicing
                </h2>
                <p class="text-sm text-gray-500">Penyatuan tagihan properti untuk seluruh utilitas (listrik, air bertingkat, pendingin distrik BTU, dan limbah)</p>
            </div>
            <div class="flex gap-2 items-center">
                <a href="{{ route('egy.bills.consolidated.export') }}" data-export-csv="consolidated-bills-export" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Rekap CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-900">Daftar Tagihan Multi-Utilitas Properti</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600" data-table="consolidated-bills-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400 font-medium">
                            <tr>
                                <th class="px-6 py-3">Nomor Faktur</th>
                                <th class="px-6 py-3">Properti ID</th>
                                <th class="px-6 py-3">Pelanggan Party ID</th>
                                <th class="px-6 py-3">Periode Tagihan</th>
                                <th class="px-6 py-3">Total Tagihan (Rp)</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($invoices as $inv)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-indigo-600">{{ $inv->invoice_number }}</td>
                                    <td class="px-6 py-4 font-semibold">Properti #{{ $inv->property_id }}</td>
                                    <td class="px-6 py-4 text-xs font-mono">Party #{{ $inv->customer_party_id }}</td>
                                    <td class="px-6 py-4 font-medium">{{ $inv->billing_period }}</td>
                                    <td class="px-6 py-4 font-bold text-gray-900">Rp {{ number_format($inv->total_amount_idr) }}</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 uppercase">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('egy.bills.consolidated.show', $inv->id) }}" class="text-xs text-indigo-600 hover:underline font-semibold">
                                            Lihat Faktur &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">Belum ada faktur konsolidasi</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($invoices->hasPages())
                    <div class="p-4 border-t border-gray-100">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
