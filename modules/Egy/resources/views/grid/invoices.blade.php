@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Consolidated Utility Invoices</h2>
            <p class="mt-1 text-sm text-gray-400">Daftar Tagihan Utilitas Properti Terkonsolidasi & Rincian Tarif TOU (WBP / LWBP)</p>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm text-left text-gray-300">
                    <thead class="bg-gray-700 text-xs uppercase text-gray-400">
                        <tr>
                            <th class="px-4 py-3">No. Faktur</th>
                            <th class="px-4 py-3">Properti</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Total Konsumsi (Wh)</th>
                            <th class="px-4 py-3">Total Tagihan (IDR)</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-gray-750">
                                <td class="px-4 py-3 font-mono text-white">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-3">{{ $inv->property_id }}</td>
                                <td class="px-4 py-3">{{ $inv->billing_period }}</td>
                                <td class="px-4 py-3 font-mono">{{ number_format($inv->total_wh) }}</td>
                                <td class="px-4 py-3 font-mono text-emerald-400">Rp {{ number_format($inv->total_consolidated_minor) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs rounded {{ $inv->status === 'SETTLED' ? 'bg-emerald-900 text-emerald-200' : 'bg-indigo-900 text-indigo-200' }}">
                                        {{ $inv->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada faktur utilitas diterbitkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
