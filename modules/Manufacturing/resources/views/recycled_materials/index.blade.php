<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight flex items-center gap-2">
                <span class="text-emerald-400">🏭</span> {{ __('Penerimaan Bahan Baku Daur Ulang Pabrik (Recycled Material Intake)') }}
            </h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                Fase 79 — Kontrak K1
            </span>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-950 min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 bg-emerald-900/50 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm flex items-center gap-2 shadow-lg">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-slate-400 font-medium">Total Tanda Terima</div>
                    <div class="text-2xl font-bold text-slate-100 mt-1">{{ number_format($stats['total_receipts']) }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-emerald-400 font-medium">Bahan Diterima (kg)</div>
                    <div class="text-2xl font-bold text-emerald-300 mt-1">{{ number_format($stats['total_grams_received'] / 1000, 2) }} kg</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-amber-400 font-medium">Nilai Terbukukan</div>
                    <div class="text-2xl font-bold text-amber-300 mt-1">Rp {{ number_format($stats['total_value_idr'], 0, ',', '.') }}</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-teal-400 font-medium">Emisi Dihindari (kg)</div>
                    <div class="text-2xl font-bold text-teal-300 mt-1">{{ number_format($stats['total_emissions_saved_g'] / 1000, 2) }} kg</div>
                </div>
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-xl">
                    <div class="text-[11px] uppercase tracking-wider text-cyan-400 font-medium">Menunggu Verifikasi</div>
                    <div class="text-2xl font-bold text-cyan-300 mt-1">{{ number_format($stats['pending_intakes_count']) }}</div>
                </div>
            </div>

            <!-- Pending Intakes Section -->
            @if($pendingIntakes->isNotEmpty())
                <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <h3 class="text-base font-bold text-amber-300 mb-4 flex items-center gap-2">
                        <span>⏳</span> Pengiriman Tiba Menunggu Intake Pabrik
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="text-xs uppercase bg-slate-800/60 text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Tracking / Manifest</th>
                                    <th class="px-4 py-3">Material & Grade</th>
                                    <th class="px-4 py-3">Pabrik Tujuan</th>
                                    <th class="px-4 py-3">Estimasi Berat</th>
                                    <th class="px-4 py-3">Status Saat Ini</th>
                                    <th class="px-4 py-3 text-right">Aksi Verifikasi Pabrik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/80">
                                @foreach($pendingIntakes as $pi)
                                    <tr class="hover:bg-slate-800/40 transition">
                                        <td class="px-4 py-3 font-mono text-xs">
                                            <div class="font-semibold text-slate-100">{{ $pi->tracking_number }}</div>
                                            <div class="text-slate-500 text-[11px]">{{ $pi->reverse_manifest_number }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-xs">
                                            <div>{{ $pi->material_code }}</div>
                                            <span class="text-slate-400">Grade {{ $pi->quality_grade }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-semibold text-emerald-400">
                                            {{ $pi->plant_id }}
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono">
                                            {{ number_format($pi->weight_grams) }} g
                                        </td>
                                        <td class="px-4 py-3 text-xs">
                                            <span class="px-2 py-0.5 rounded bg-cyan-900/50 text-cyan-300 border border-cyan-700">
                                                {{ $pi->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('manufacturing.recycled-materials.intake') }}" method="POST" class="inline-flex gap-2">
                                                @csrf
                                                <input type="hidden" name="shipment_id" value="{{ $pi->id }}">
                                                <input type="hidden" name="decision" value="accept">
                                                <input type="hidden" name="verified_weight_grams" value="{{ $pi->weight_grams }}">
                                                <input type="hidden" name="quality_grade" value="{{ $pi->quality_grade }}">
                                                <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-medium shadow">
                                                    ✓ Terima ke Lot
                                                </button>
                                            </form>
                                            <form action="{{ route('manufacturing.recycled-materials.intake') }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="shipment_id" value="{{ $pi->id }}">
                                                <input type="hidden" name="decision" value="reject">
                                                <input type="hidden" name="rejection_reason" value="Kualitas di bawah standar">
                                                <button type="submit" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded text-xs shadow">
                                                    ✗ Tolak
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Receipts History -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                    <span>📦</span> Riwayat Penerimaan Bahan Baku Sekunder (mfg_recycled_receipts)
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-800/60 text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Receipt No</th>
                                <th class="px-4 py-3">Material & Grade</th>
                                <th class="px-4 py-3">Berat Bersih</th>
                                <th class="px-4 py-3">Harga & Nilai IDR</th>
                                <th class="px-4 py-3">Lot Manufaktur</th>
                                <th class="px-4 py-3">Emisi Scope 3 Dihindari</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($receipts as $rc)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-emerald-400">
                                        #{{ $rc->id }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div>{{ $rc->material_code }}</div>
                                        <span class="text-slate-400">Grade {{ $rc->grade_code }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        {{ number_format($rc->quantity_grams) }} g ({{ round($rc->quantity_grams / 1000, 2) }} kg)
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono text-amber-300">
                                        <div>Rp {{ number_format($rc->total_value_idr, 0, ',', '.') }}</div>
                                        <div class="text-slate-500 text-[10px]">@ Rp {{ number_format($rc->unit_price_per_kg_idr, 0, ',', '.') }}/kg</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        @if($rc->material_lot_id)
                                            <div class="text-slate-200">{{ substr($rc->material_lot_id, 0, 13) }}...</div>
                                        @else
                                            <span class="text-slate-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-teal-300 font-mono">
                                        {{ number_format(($rc->quantity_grams * 1.8) / 1000, 2) }} kg CO₂e
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                            ACCEPTED
                                        </span>
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">
                                        Belum ada data penerimaan bahan daur ulang.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $receipts->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
