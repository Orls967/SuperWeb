<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Foreign Exchange (FX) & PSAK 10 Revaluation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white shadow rounded-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Lot Valas Aktif (Subledger)</h3>
                    <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">Metode FIFO / Spesifik</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lot ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valas</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tgl Perolehan</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sisa Valas</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Kurs Buku</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nilai Buku IDR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($lots as $lot)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ substr($lot->id, 0, 8) }}</td>
                                    <td class="px-4 py-2 font-bold">{{ $lot->currency }}</td>
                                    <td class="px-4 py-2 text-sm">{{ $lot->lot_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-right text-sm">{{ number_format($lot->remaining_amount) }}</td>
                                    <td class="px-4 py-2 text-right text-sm">{{ $lot->current_carrying_rate_num }}/{{ $lot->current_carrying_rate_den }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold">{{ number_format($lot->current_carrying_idr) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-2 text-center text-sm text-gray-500">Tidak ada lot valas aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-6 bg-white shadow rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Riwayat Revaluasi Kurs Akhir Periode</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valas</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nilai Buku</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Nilai Wajar</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Selisih Kurs IDR</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($revaluations as $rev)
                                <tr>
                                    <td class="px-4 py-2 font-bold">{{ $rev->period }}</td>
                                    <td class="px-4 py-2">{{ $rev->currency }}</td>
                                    <td class="px-4 py-2 text-right text-sm">{{ number_format($rev->book_functional_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm">{{ number_format($rev->revalued_functional_idr) }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-semibold {{ $rev->gain_loss_idr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($rev->gain_loss_idr) }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-xs">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800">{{ $rev->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-2 text-center text-sm text-gray-500">Belum ada revaluasi kurs tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
