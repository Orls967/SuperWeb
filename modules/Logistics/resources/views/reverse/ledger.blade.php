<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight flex items-center gap-2">
                <span class="text-teal-400">📊</span> {{ __('Buku Besar Ekonomi Sirkular (Circular Economy Ledger)') }}
            </h2>
            <a href="{{ route('logistics.reverse.shipments.index') }}" class="px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700 transition">
                ← Kembali ke Shipment Reverse
            </a>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-950 min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Balances Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-slate-400 font-medium">Akun Kliring Bahan Daur Ulang</div>
                    <div class="text-xs font-mono text-slate-500 mt-1">mfg:recycled_material_clearing:IDR</div>
                    <div class="text-2xl font-bold text-amber-300 mt-2">
                        Rp {{ number_format(abs($accounts['mfg_recycled_clearing']), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Saldo kredit (kewajiban kliring pabrik)</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-emerald-400 font-medium">Persediaan Bahan Baku (Sekunder)</div>
                    <div class="text-xs font-mono text-slate-500 mt-1">inv:materials:IDR</div>
                    <div class="text-2xl font-bold text-emerald-300 mt-2">
                        Rp {{ number_format(abs($accounts['inv_materials']), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Saldo debit (aset persediaan manufaktur)</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-rose-400 font-medium">Beban Logistik Balik (Carrier Cost)</div>
                    <div class="text-xs font-mono text-slate-500 mt-1">lgx:carrier_cost:IDR</div>
                    <div class="text-2xl font-bold text-rose-300 mt-2">
                        Rp {{ number_format(abs($accounts['lgx_carrier_cost']), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Saldo debit (akumulasi biaya logistik)</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-cyan-400 font-medium">Hold Utang Carrier</div>
                    <div class="text-xs font-mono text-slate-500 mt-1">lgx:carrier_payable_on_hold:IDR</div>
                    <div class="text-2xl font-bold text-cyan-300 mt-2">
                        Rp {{ number_format(abs($accounts['lgx_carrier_hold']), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">Saldo kredit (klaim/breach tertahan)</div>
                </div>
            </div>

            <!-- Recent Shipments with Intake Completed -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                    <span>📑</span> Penerimaan Manufaktur Terakhir yang Telah Dibukukan
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-800/60 text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Tracking</th>
                                <th class="px-4 py-3">Pabrik & Receipt ID</th>
                                <th class="px-4 py-3">Material & Grade</th>
                                <th class="px-4 py-3">Berat Timbang</th>
                                <th class="px-4 py-3">Biaya Carrier</th>
                                <th class="px-4 py-3">Waktu Penerimaan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($recentReverseShipments as $sh)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-100">
                                        {{ $sh->tracking_number }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div class="font-semibold text-emerald-400">{{ $sh->plant_id }}</div>
                                        <div class="text-slate-500 text-[11px]">Receipt #{{ $sh->plant_receipt_id }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div>{{ $sh->material_code }}</div>
                                        <span class="text-slate-400">Grade {{ $sh->quality_grade }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        {{ number_format($sh->verified_weight_grams ?? $sh->weight_grams) }} g
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono text-rose-300">
                                        Rp {{ number_format($sh->carrier_cost_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-400">
                                        {{ $sh->plant_received_at ?? $sh->updated_at }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-500 text-sm">
                                        Belum ada penerimaan material sirkular yang terbukukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
