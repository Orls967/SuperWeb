<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">BOM Multi-Level Explosion &amp; Engineering Structure</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Pohon Struktur Produk (Bill of Materials)</h3>
                <p class="text-sm text-gray-500 mb-6">Visualisasi hierarki multi-level komponen perakitan dengan faktor scrap, yield, dan sub-rakitan phantom.</p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                        <span class="text-xs font-semibold text-indigo-600 uppercase">Kedalaman Level</span>
                        <p class="text-2xl font-black text-indigo-900 mt-1">Multi-Level (5+)</p>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-semibold text-emerald-600 uppercase">Faktor Scrap Terhitung</span>
                        <p class="text-2xl font-black text-emerald-900 mt-1">+1.5% s/d +3%</p>
                    </div>
                    <div class="p-4 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-xs font-semibold text-amber-600 uppercase">Integritas Hash</span>
                        <p class="text-2xl font-black text-amber-900 mt-1">SHA-256 Valid</p>
                    </div>
                    <div class="p-4 bg-purple-50 border border-purple-100 rounded-lg">
                        <span class="text-xs font-semibold text-purple-600 uppercase">Status Versi</span>
                        <p class="text-2xl font-black text-purple-900 mt-1">Active Released</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Level</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">Kode Material</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Kuantitas Dasar</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-700">Scrap %</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-700">Tipe Node</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-3 font-bold text-indigo-600">.1</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">SUB-ASSY-CHASSIS-01</td>
                                <td class="px-4 py-3 text-right font-mono">1.000000</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">0.00%</td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 text-xs rounded bg-purple-100 text-purple-800 font-semibold">Sub-Assembly</span></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-bold text-indigo-600">..2</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">RAW-STEEL-TUBE-20MM</td>
                                <td class="px-4 py-3 text-right font-mono">4.250000</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">2.50%</td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 text-xs rounded bg-emerald-100 text-emerald-800 font-semibold">Raw Material</span></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-bold text-indigo-600">..2</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">FASTENER-M8-BOLT</td>
                                <td class="px-4 py-3 text-right font-mono">16.000000</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">1.00%</td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-800 font-semibold">Hardware</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
