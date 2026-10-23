<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Directed Putaway &amp; Slotting</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Directed Inbound Putaway Engine</h3>
                <p class="text-sm text-gray-500 mb-4">Secara otomatis mengarahkan penempatan barang ke bin optimal berdasarkan zona, kapasitas volume, dan klasifikasi perputaran barang.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Total Bin Aktif</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">{{ count($bins ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Zona Penyimpanan</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">{{ count($zones ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">Strategi Slotting</span>
                        <p class="text-2xl font-black text-purple-900 mt-1">ABC Velocity + Cube</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Kode Bin</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Rak</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Kapasitas</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Pick-Face</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($bins ?? [] as $bin)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $bin->code }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $bin->rack_id }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 font-medium">{{ number_format($bin->capacity_units) }} unit</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $bin->is_pick_face ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $bin->is_pick_face ? 'Pick Face' : 'Reserve' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Aktif</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada data bin penyimpanan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
