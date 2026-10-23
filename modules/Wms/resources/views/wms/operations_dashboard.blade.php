<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Warehouse Operations Command Dashboard</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Pusat Komando Operasional Gudang &amp; Logistik</h3>
                <p class="text-sm text-gray-500 mb-6">Ringkasan kapasitas, okupansi, aliran transfer, dan metrik produktivitas seluruh pusat distribusi.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Total DC / Gudang</span>
                        <p class="text-3xl font-black text-indigo-900 mt-1">{{ count($warehouses ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Okupansi Rata-rata</span>
                        <p class="text-3xl font-black text-emerald-900 mt-1">78.2%</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Throughput Harian</span>
                        <p class="text-3xl font-black text-amber-900 mt-1">14,250 unit</p>
                    </div>
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">On-Time Dispatch</span>
                        <p class="text-3xl font-black text-purple-900 mt-1">99.1%</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($warehouses ?? [] as $wh)
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-gray-900">{{ $wh->code }}</h4>
                                    <p class="text-xs text-gray-500">{{ $wh->name }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-xs font-bold rounded bg-indigo-100 text-indigo-800 uppercase">{{ $wh->kind }}</span>
                            </div>
                            <div class="text-xs text-gray-600">Kota: <strong>{{ $wh->city ?? 'Sentral' }}</strong></div>
                            <div class="text-xs text-gray-600">Total Zona: <strong>{{ $wh->zones_count ?? 0 }} zona</strong></div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: 75%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-3 text-center py-6 text-gray-400">Belum ada gudang terdaftar.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
