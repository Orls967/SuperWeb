<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kepatuhan Batas Emisi Karbon Ekspor (EU CBAM)') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('egy.cbam.export') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh Deklarasi (CSV)
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Metrik Saldo CBAM -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="text-sm font-medium text-gray-500">Total Komoditas Diekspor</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format(($total_product_kg ?? 0) / 1000, 2, ',', '.') }} Ton</div>
                    <div class="mt-1 text-xs text-indigo-600">Baja, Semen, Aluminium & Pupuk</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-amber-500">
                    <div class="text-sm font-medium text-gray-500">Emisi Tertanam (Embedded)</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format(($total_embedded_co2e_kg ?? 0) / 1000, 2, ',', '.') }} Ton CO₂e</div>
                    <div class="mt-1 text-xs text-amber-600">Berdasarkan Regulasi EU 2023/956</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                    <div class="text-sm font-medium text-gray-500">Liabilitas Pajak Karbon CBAM</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">Rp {{ number_format($total_liability_idr ?? 0, 0, ',', '.') }}</div>
                    <div class="mt-1 text-xs text-red-600">Dicadangkan ke buku besar Ledger</div>
                </div>
            </div>

            <!-- Visualisasi Benchmark Emisi Uni Eropa (SVG Comparison) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Perbandingan Intensitas Emisi Pabrik vs Patokan Benchmark Uni Eropa (kg CO₂e / Ton Produk)
                </h3>
                <div class="w-full overflow-x-auto">
                    <svg class="w-full h-36" viewBox="0 0 800 130">
                        <!-- Background baseline -->
                        <line x1="50" y1="100" x2="750" y2="100" stroke="#e5e7eb" stroke-width="1.5"/>

                        <!-- Bar 1: EU Benchmark (1300 kg/ton) -->
                        <rect x="120" y="45" width="180" height="55" rx="4" fill="#10b981"/>
                        <text x="210" y="35" font-size="11" font-weight="bold" fill="#065f46" text-anchor="middle">EU BENCHMARK: 1.300 kg/t</text>
                        <text x="210" y="75" font-size="11" font-weight="bold" fill="#ffffff" text-anchor="middle">Batas Bebas Beban</text>

                        <!-- Bar 2: Pabrik Faktual (2100 kg/ton) -->
                        <rect x="420" y="15" width="180" height="85" rx="4" fill="#ef4444"/>
                        <text x="510" y="10" font-size="11" font-weight="bold" fill="#991b1b" text-anchor="middle">EMISI SPESIFIK: 2.100 kg/t</text>
                        <text x="510" y="55" font-size="11" font-weight="bold" fill="#ffffff" text-anchor="middle">+800 kg/t Kena CBAM</text>
                    </svg>
                </div>
            </div>

            <!-- Tabel Deklarasi Kontainer Ekspor -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4">Daftar Deklarasi Pabean Kontainer Ekspor</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Nomor Deklarasi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">No. Kontainer</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">HS Code</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Tonase (kg)</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Emisi (kg CO₂e)</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Liabilitas (€)</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Liabilitas (IDR)</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($declarations as $d)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">{{ $d->declaration_number }}</td>
                                    <td class="px-4 py-3 font-mono font-bold">{{ $d->container_number }}</td>
                                    <td class="px-4 py-3 text-center font-mono">{{ $d->hs_code }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($d->product_tonnage_kg, 0, ',', '.') }} kg</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format($d->gross_embedded_emissions_kg_co2e, 0, ',', '.') }} kg</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">€ {{ number_format($d->total_cbam_liability_eur_minor / 100, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-red-600">Rp {{ number_format($d->total_cbam_liability_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800 font-bold uppercase">{{ $d->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-400">Belum ada deklarasi ekspor CBAM tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
