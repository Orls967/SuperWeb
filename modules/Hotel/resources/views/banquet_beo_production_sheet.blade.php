@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Breadcrumbs & Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <nav class="flex text-xs text-slate-400 gap-2 mb-2">
                    <a href="{{ route('hotel.mice.pipeline') }}" class="hover:text-slate-200">MICE Sales</a>
                    <span>/</span>
                    <span class="text-indigo-400">Banquet Event Order (BEO)</span>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="p-2 bg-amber-500/20 text-amber-400 rounded-lg border border-amber-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-white">Lembar Kerja Produksi Banquet BEO & BOM Resep</h1>
                        <p class="text-sm text-slate-400">Pembedahan gramatur resep dapur H15, varians penuangan minuman bar, dan pemotongan batch inventaris Resto</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak Lembar Dapur
                </button>
                <button type="button" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-amber-600/30 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Mulai Produksi (Mise en place)
                </button>
            </div>
        </div>

        <!-- BEO Master Banner -->
        <div class="bg-gradient-to-r from-slate-800 via-slate-800/90 to-slate-800/60 border border-slate-700 rounded-xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">BEO-2026-089</span>
                    <span class="text-xs text-slate-400">Grand Ballroom & Foyer</span>
                    <span class="px-2 py-0.5 text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded">STATUS: CONFIRMED</span>
                </div>
                <h2 class="text-xl font-bold text-white">Gala Dinner: ASEAN Energy Ministerial Summit</h2>
                <p class="text-sm text-slate-300">Jadwal: 15 November 2026 &bull; Sesi: Gala Dinner (18:00 - 22:00) &bull; Durasi: 4.0 Jam</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/50">
                    <div class="text-xs text-slate-400">Tamu Terjamin</div>
                    <div class="text-xl font-bold text-white">300 Pax</div>
                </div>
                <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/50">
                    <div class="text-xs text-slate-400">Porsi Dapur (+5%)</div>
                    <div class="text-xl font-bold text-amber-400">315 Porsi</div>
                </div>
                <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/50">
                    <div class="text-xs text-slate-400">Pramusaji Kasual</div>
                    <div class="text-xl font-bold text-blue-400">15 Orang</div>
                </div>
                <div class="bg-slate-900/60 p-3 rounded-lg border border-slate-700/50">
                    <div class="text-xs text-slate-400">Koki & Steward</div>
                    <div class="text-xl font-bold text-purple-400">8 Orang</div>
                </div>
            </div>
        </div>

        <!-- Two Columns: Recipe BOM Breakdown & Beverage Shrinkage -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Food Recipe BOM Table -->
            <div class="lg:col-span-2 bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-white text-base">Bill of Materials (BOM) Resep Makanan</h3>
                        <p class="text-xs text-slate-400">Kebutuhan kotor bahan mentah dengan penyesuaian yield loss factor</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs bg-slate-700 text-slate-300 rounded font-mono">Modul Resto Linked</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-800 text-xs uppercase font-semibold text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="p-3">Item Resep / Bahan Baku</th>
                                <th class="p-3 text-center">Porsi Netto</th>
                                <th class="p-3 text-center">Yield Loss</th>
                                <th class="p-3 text-right">Kebutuhan Kotor</th>
                                <th class="p-3 text-right">Total Biaya (COGS)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50 font-mono text-xs">
                            <tr>
                                <td class="p-3 font-sans">
                                    <div class="font-medium text-white">Australian Wagyu Beef MB7</div>
                                    <div class="text-xs text-slate-400">Menu: Pan-Seared Wagyu with Truffle Jus</div>
                                </td>
                                <td class="p-3 text-center">200.0 g</td>
                                <td class="p-3 text-center text-amber-400">10.0%</td>
                                <td class="p-3 text-right font-bold text-white">70.00 kg</td>
                                <td class="p-3 text-right text-emerald-400">IDR 21.000.000</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-sans">
                                    <div class="font-medium text-white">Norwegian Fresh Salmon Fillet</div>
                                    <div class="text-xs text-slate-400">Menu: Smoked Salmon Carpaccio</div>
                                </td>
                                <td class="p-3 text-center">90.0 g</td>
                                <td class="p-3 text-center text-amber-400">8.0%</td>
                                <td class="p-3 text-right font-bold text-white">30.82 kg</td>
                                <td class="p-3 text-right text-emerald-400">IDR 8.629.600</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-sans">
                                    <div class="font-medium text-white">Wild Forest Mushrooms Assorted</div>
                                    <div class="text-xs text-slate-400">Menu: Truffle Mushroom Veloute</div>
                                </td>
                                <td class="p-3 text-center">120.0 g</td>
                                <td class="p-3 text-center text-amber-400">5.0%</td>
                                <td class="p-3 text-right font-bold text-white">39.79 kg</td>
                                <td class="p-3 text-right text-emerald-400">IDR 4.774.800</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-700 bg-slate-800/80 font-bold text-xs">
                            <tr>
                                <td colspan="4" class="p-3 text-right font-sans text-slate-300">Total Food COGS (BOM Bahan):</td>
                                <td class="p-3 text-right text-emerald-400 font-mono">IDR 34.404.400</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Right Col: Beverage Pouring & Staffing Breakdown -->
            <div class="space-y-6">
                <!-- Beverage Pouring Variance -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white text-base">Minuman & Pour Shrinkage</h3>
                        <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Toleransi 5%</span>
                    </div>

                    <div class="space-y-3">
                        <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-white">Château Margaux Red Wine (750ml)</span>
                                <span class="font-mono text-emerald-400">IDR 18.500.000</span>
                            </div>
                            <div class="mt-2 text-xs text-slate-400 flex items-center justify-between">
                                <span>331 Gelas &bull; 150ml/gelas</span>
                                <span class="font-bold text-slate-200">67 Botol Diperlukan</span>
                            </div>
                            <div class="w-full bg-slate-700 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-indigo-500 h-full rounded-full" style="width: 85%"></div>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-900/60 rounded-lg border border-slate-700">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-white">Fresh Orange Juice Carafe (1000ml)</span>
                                <span class="font-mono text-emerald-400">IDR 2.905.000</span>
                            </div>
                            <div class="mt-2 text-xs text-slate-400 flex items-center justify-between">
                                <span>331 Gelas &bull; 250ml/gelas</span>
                                <span class="font-bold text-slate-200">83 Botol Diperlukan</span>
                            </div>
                            <div class="w-full bg-slate-700 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-amber-500 h-full rounded-full" style="width: 92%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Labor & Shift Allocations -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-white text-base">Alokasi Staf & Upah Jamuan</h3>
                        <span class="text-xs text-blue-400 font-mono">Rate: IDR 50.000/jam</span>
                    </div>

                    <div class="space-y-2 text-xs text-slate-300">
                        <div class="flex justify-between py-1 border-b border-slate-700/60">
                            <span>Pramusaji (1 per 20 pax):</span>
                            <span class="font-mono font-bold text-white">15 Staf (60 Jam)</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-700/60">
                            <span>Koki / Stewarding (1 per 40 pax):</span>
                            <span class="font-mono font-bold text-white">8 Staf (32 Jam)</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-700/60">
                            <span>Total Jam Kerja Staf Kasual:</span>
                            <span class="font-mono text-amber-400">92 Jam</span>
                        </div>
                        <div class="flex justify-between pt-2 text-sm font-bold">
                            <span class="text-white">Estimasi Biaya Tenaga Kerja:</span>
                            <span class="text-emerald-400 font-mono">IDR 4.600.000</span>
                        </div>
                    </div>
                </div>

                <!-- Kitchen Equipment & Thermal Holding Checklist -->
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-3">
                    <h3 class="font-bold text-white text-base flex items-center justify-between">
                        <span>Checklist Peralatan & Pemanas Dapur</span>
                        <span class="text-xs text-emerald-400 font-mono">HACCP &ge; 63&deg;C</span>
                    </h3>
                    <ul class="text-xs text-slate-300 space-y-2">
                        <li class="flex items-center justify-between p-2 rounded bg-slate-900/50 border border-slate-800">
                            <span>Roll-Top Chafing Dish (Kuningan VIP)</span>
                            <span class="font-mono font-semibold text-slate-200">12 Unit Terisi</span>
                        </li>
                        <li class="flex items-center justify-between p-2 rounded bg-slate-900/50 border border-slate-800">
                            <span>Thermal Hot Box Cabinet (68&deg;C)</span>
                            <span class="font-mono font-semibold text-emerald-400">Optimal (4 Box)</span>
                        </li>
                        <li class="flex items-center justify-between p-2 rounded bg-slate-900/50 border border-slate-800">
                            <span>Plate Warmer Stacking Cart</span>
                            <span class="font-mono font-semibold text-slate-200">350 Piring Siap</span>
                        </li>
                        <li class="flex items-center justify-between p-2 rounded bg-slate-900/50 border border-slate-800">
                            <span>Dispenser Kopi & Teh Panas Tekanan Tinggi</span>
                            <span class="font-mono font-semibold text-amber-400">4 Stasiun Foyer</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Allergen Compliance Matrix Table -->
        @include('hotel::components.beo_allergen_matrix_table')
    </div>
</div>
@endsection
