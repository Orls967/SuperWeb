<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bank Statement Reconciliation (3-Stage D2)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Mutasi Rekening Bank & Status Pemadanan</h3>
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ ($unreconciledCount ?? 0) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                        {{ $unreconciledCount ?? 0 }} Belum Terekonsiliasi
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Referensi</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nominal</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($statements as $stmt)
                                <tr>
                                    <td class="px-4 py-2 text-sm">{{ $stmt->transaction_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $stmt->reference_no }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $stmt->description }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold">{{ number_format($stmt->amount) }}</td>
                                    <td class="px-4 py-2 text-center text-xs">
                                        @if($stmt->is_reconciled)
                                            <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">Matched</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Unmatched</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada mutasi rekening bank.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

