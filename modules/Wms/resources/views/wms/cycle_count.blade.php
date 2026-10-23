<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Cycle Count &amp; 4-Eyes Stock Adjustment</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Audit Fisik Stok &amp; Prinsip Empat Mata (Four-Eyes)</h3>
                <p class="text-sm text-gray-500 mb-4">Setiap penyesuaian selisih stok (variance) wajib divalidasi dan disetujui oleh pengguna berwenang yang berbeda dari penghitung fisik.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="p-4 bg-teal-50 border border-teal-100 rounded-lg">
                        <span class="text-xs font-semibold text-teal-600 uppercase">Siklus Hitung Selesai</span>
                        <p class="text-2xl font-black text-teal-900 mt-1">{{ count($counts ?? []) }}</p>
                    </div>
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Akurasi Rata-rata</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">99.85%</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Kontrol SoD</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">Four-Eyes Strict</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Nomor Dokumen</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Sistem Qty</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Hitung Fisik</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Selisih</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Akurasi</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($counts ?? [] as $count)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ $count->number }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">{{ number_format((float) $count->system_qty, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">{{ number_format((float) $count->counted_qty, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold {{ (float) $count->variance_qty != 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        {{ number_format((float) $count->variance_qty, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium text-gray-700">{{ $count->accuracy_percent }}%</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $count->status === 'applied' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $count->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada cycle count tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
