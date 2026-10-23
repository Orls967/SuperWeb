<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight flex items-center gap-2">
                <span class="text-emerald-400">♻️</span> {{ __('Reverse Logistics & Circular Economy Engine') }}
            </h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                Fase 79 — Circular Verified
            </span>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-950 min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div id="alert-success" class="p-4 bg-emerald-900/50 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm flex items-center gap-2 shadow-lg">
                    <span>✓</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- KPI Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-slate-400 font-medium">Total Reverse Orders</div>
                    <div class="text-2xl font-bold text-slate-100 mt-2" id="kpi-total-orders">{{ number_format($stats['total_orders']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">
                        <span class="text-amber-400 font-semibold">{{ $stats['scheduled_orders'] }}</span> Scheduled / 
                        <span class="text-emerald-400 font-semibold">{{ $stats['processed_orders'] }}</span> Processed
                    </div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-emerald-400 font-medium">Appraised Circular Value</div>
                    <div class="text-2xl font-bold text-emerald-300 mt-2" id="kpi-total-value">Rp {{ number_format($stats['total_appraised_idr'], 0, ',', '.') }}</div>
                    <div class="text-xs text-slate-500 mt-1">Valued via Pricing Engine to Ledger</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-teal-400 font-medium">Avoided CO₂ Emissions</div>
                    <div class="text-2xl font-bold text-teal-300 mt-2" id="kpi-avoided-co2">{{ number_format($stats['total_avoided_co2'], 1) }} kg</div>
                    <div class="text-xs text-slate-500 mt-1">Faktor 2.1 kg CO₂e / kg material</div>
                </div>

                <div class="p-5 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                    <div class="text-xs uppercase tracking-wider text-cyan-400 font-medium">Total Recovered Material</div>
                    <div class="text-2xl font-bold text-cyan-300 mt-2" id="kpi-total-kg">{{ number_format($stats['total_items_kg'], 1) }} kg/L</div>
                    <div class="text-xs text-slate-500 mt-1">Saved from landfill into manufacturing</div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                    <span>📦</span> Komposisi Material Daur Ulang & Nilai Sirkular
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @forelse($categoryStats as $cat)
                        <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-xl">
                            <div class="text-xs font-semibold text-slate-300 truncate">{{ str_replace('_', ' ', $cat->item_category) }}</div>
                            <div class="text-sm font-bold text-emerald-400 mt-1">{{ number_format($cat->total_qty, 1) }} units</div>
                            <div class="text-xs text-slate-400">Rp {{ number_format($cat->total_val, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <div class="col-span-full text-center text-slate-500 py-3 text-sm">Belum ada kategori material yang dicatat.</div>
                    @endforelse
                </div>
            </div>

            <!-- Ingest Trigger / Create Reverse Order Form -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <h3 class="text-base font-bold text-slate-200 mb-4 flex items-center gap-2">
                    <span>⚡</span> Trigger Event Penjemputan Limbah / Retur Sirkular
                </h3>
                <form id="form-create-reverse-order" action="{{ route('logistics.reverse.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="event_trigger_type" class="block text-xs font-medium text-slate-400">Event Trigger</label>
                            <select id="event_trigger_type" name="event_trigger_type" class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                                <option value="resto.waste_bulk">resto.waste_bulk (Jelantah Resto)</option>
                                <option value="auto.oil_used">auto.oil_used (Oli Bekas Bengkel)</option>
                                <option value="store.return">store.return (Retur Retail/Store)</option>
                                <option value="mfg.scrap">mfg.scrap (Scrap Manufaktur)</option>
                                <option value="hsp.bio_waste">hsp.bio_waste (Limbah B3 Medis)</option>
                            </select>
                        </div>
                        <div>
                            <label for="source_reference_id" class="block text-xs font-medium text-slate-400">Source Batch/Ref ID</label>
                            <input type="text" id="source_reference_id" name="source_reference_id" value="REF-CIRCULAR-{{ time() }}" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                        </div>
                        <div>
                            <label for="pickup_location" class="block text-xs font-medium text-slate-400">Lokasi Penjemputan</label>
                            <input type="text" id="pickup_location" name="pickup_location" value="Central Hub Banjarmasin" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                        </div>
                        <div>
                            <label for="destination_facility" class="block text-xs font-medium text-slate-400">Fasilitas Pengolah</label>
                            <input type="text" id="destination_facility" name="destination_facility" value="Biodiesel Plant Cikarang" required class="mt-1 block w-full rounded-xl bg-slate-950 border border-slate-700 text-slate-100 text-sm px-3 py-2">
                        </div>
                    </div>

                    <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-xl space-y-3">
                        <div class="text-xs font-semibold text-slate-300">Detail Material Sirkular</div>
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                            <div class="md:col-span-2">
                                <input type="hidden" name="items[0][category]" value="COOKING_OIL_JELANTAH">
                                <input type="text" name="items[0][description]" value="Minyak jelantah kualitas A" placeholder="Deskripsi" required class="w-full rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5">
                            </div>
                            <div>
                                <input type="number" step="0.1" name="items[0][quantity]" value="100.0" placeholder="Qty" required class="w-full rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5">
                            </div>
                            <div>
                                <select name="items[0][uom]" class="w-full rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs px-2 py-1.5">
                                    <option value="LITER">LITER</option>
                                    <option value="KG">KG</option>
                                    <option value="UNIT">UNIT</option>
                                </select>
                            </div>
                            <div>
                                <input type="text" name="items[0][recycling_target]" value="BIODIESEL_REFINERY" placeholder="Target" required class="w-full rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5">
                            </div>
                            <div>
                                <input type="number" name="items[0][appraised_unit_price_idr]" value="8500" placeholder="Harga/satuan Rp" required class="w-full rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" id="btn-submit-reverse-order" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-medium text-sm transition shadow-lg shadow-emerald-900/30">
                            + Terbitkan Manifest & Reverse Order
                        </button>
                    </div>
                </form>
            </div>

            <!-- Reverse Orders Table -->
            <div class="p-6 bg-slate-900/80 border border-slate-800 rounded-2xl backdrop-blur-xl shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-200 flex items-center gap-2">
                        <span>📋</span> Daftar Reverse Shipment & Manifest Kustodi
                    </h3>
                    <span class="text-xs text-slate-400">Total: {{ $orders->total() }} record</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-4 py-3">Kode Order</th>
                                <th class="px-4 py-3">Event Trigger</th>
                                <th class="px-4 py-3">Rute (Asal → Tujuan)</th>
                                <th class="px-4 py-3">Nilai Taksiran</th>
                                <th class="px-4 py-3">Avoided CO₂</th>
                                <th class="px-4 py-3">Manifest & Hash Kustodi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($orders as $ord)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="px-4 py-3 font-semibold text-slate-100 font-mono text-xs">{{ $ord->reverse_code }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs bg-slate-800 text-slate-300 border border-slate-700">{{ $ord->event_trigger_type }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <div>{{ $ord->pickup_location }}</div>
                                        <div class="text-slate-500">→ {{ $ord->destination_facility }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-emerald-400 font-semibold text-xs">
                                        Rp {{ number_format($ord->total_appraised_value_idr, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-teal-400 text-xs">
                                        {{ number_format((float) $ord->avoided_emissions_kg_co2, 2) }} kg
                                    </td>
                                    <td class="px-4 py-3 text-xs font-mono">
                                        <div class="text-slate-300">{{ $ord->manifest_number }}</div>
                                        <div class="text-slate-500 text-[10px] truncate max-w-[140px]" title="{{ $ord->custody_hash }}">{{ $ord->custody_hash }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($ord->status === 'PROCESSED')
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">PROCESSED</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30">SCHEDULED</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($ord->status !== 'PROCESSED')
                                            <form action="{{ route('logistics.reverse.intake', $ord->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" id="btn-intake-{{ $ord->id }}" class="px-3 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-xs font-medium transition shadow">
                                                    Intake ke Pabrik
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-slate-500 text-xs">Telah Dibukukan</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500 text-sm">
                                        Belum ada order reverse logistics. Silakan gunakan form di atas untuk membuat order baru.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
