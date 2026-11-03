<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manajemen Ronde Lelang Periodik (Call Auction Clearing)') }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Ronde Lelang Harga Seragam</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Kode Ronde</th>
                            <th class="px-4 py-2 text-left">Material</th>
                            <th class="px-4 py-2 text-left">Grade</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Harga Kliring/kg</th>
                            <th class="px-4 py-2 text-right">Volume Kliring (g)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rounds as $round)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $round->round_code }}</td>
                                <td class="px-4 py-2">{{ $round->material_code }}</td>
                                <td class="px-4 py-2">{{ $round->grade_code }}</td>
                                <td class="px-4 py-2">{{ $round->status }}</td>
                                <td class="px-4 py-2 text-right">
                                    {{ $round->clearing_price_per_kg_idr ? 'Rp '.number_format($round->clearing_price_per_kg_idr) : '-' }}
                                </td>
                                <td class="px-4 py-2 text-right">{{ number_format($round->cleared_volume_grams) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">Belum ada ronde lelang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

