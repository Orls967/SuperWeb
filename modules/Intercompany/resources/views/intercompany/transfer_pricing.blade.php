<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Aturan & Kepatuhan Penetapan Harga Transfer (Transfer Pricing)') }}
        </h2>
    </x-slot>
    <x-enterprise.module-dashboard module="Intercompany" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Rentang Wajar Arm's Length (PMK-172 / OECD Guidelines)</h3>
                        <p class="text-xs text-gray-500 mt-1">Metode CPM, TNMM, CUP, dan Resale Price dengan batas margin interkuartil (IQR) independen.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded bg-amber-100 text-amber-800">
                        Algoritma Bersertifikat D4
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Aturan</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori Produk</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode TP</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batas Min (IQR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Median Target</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batas Max (IQR)</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sumber Benchmark</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($rules as $r)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-gray-900">{{ $r->rule_code }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-semibold">{{ $r->product_category }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-blue-600">{{ $r->tp_method }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-sm text-gray-800">{{ number_format($r->min_margin_bps / 100, 2) }}%</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono font-bold text-sm text-green-700">{{ number_format($r->median_margin_bps / 100, 2) }}%</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-sm text-gray-800">{{ number_format($r->max_margin_bps / 100, 2) }}%</td>
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $r->benchmark_industry_source }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada aturan penetapan harga transfer tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
