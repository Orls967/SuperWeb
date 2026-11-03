<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bursa Material Daur Ulang & Limbah Industri (Circular Market)') }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Penawaran Aktif</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Kode Listing</th>
                            <th class="px-4 py-2 text-left">Material</th>
                            <th class="px-4 py-2 text-left">Grade</th>
                            <th class="px-4 py-2 text-right">Stok (gram)</th>
                            <th class="px-4 py-2 text-right">Harga/kg (IDR)</th>
                            <th class="px-4 py-2 text-left">Mode</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($listings as $item)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $item->listing_code }}</td>
                                <td class="px-4 py-2">{{ $item->material_code }}</td>
                                <td class="px-4 py-2">{{ $item->grade_code }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($item->available_grams) }}</td>
                                <td class="px-4 py-2 text-right">Rp {{ number_format($item->unit_price_per_kg_idr) }}</td>
                                <td class="px-4 py-2">{{ $item->mode }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">Belum ada penawaran aktif di pasar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
