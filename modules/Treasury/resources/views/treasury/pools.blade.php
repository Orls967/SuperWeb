<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cash Pooling & Group Zero-Balancing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Struktur Cash Pooling Multi-Entitas</h3>
                    <span class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-800">No Negative Balance Guarantee</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama Pool</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rekening Header (Induk)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rekening Sub (Anak)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Target Balance</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sweep Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($pools as $pool)
                                <tr>
                                    <td class="px-4 py-2 font-bold">{{ $pool->pool_name }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $pool->headerAccount?->account_number }} ({{ $pool->headerAccount?->bank_name }})</td>
                                    <td class="px-4 py-2 text-sm">{{ $pool->subAccount?->account_number }} ({{ $pool->subAccount?->bank_name }})</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold">{{ number_format($pool->target_balance_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-blue-600 font-bold">
                                        {{ number_format($pool->last_swept_amount_idr) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada cash pool terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

