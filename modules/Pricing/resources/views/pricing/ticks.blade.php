<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Algorithmic &amp; Surge Pricing Engine (Detik-per-Detik) — Fase 81</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Fase 81:</strong> Mesin harga real-time berbasis permintaan, guardrail floor (1.15x HPP) / ceiling (HET), price waterfall audit, dan pembekuan dokumen immutable quote timelock.
            </div>

            <!-- SKU Filter & Analytics Summary -->
            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
                    <form method="GET" action="{{ route('pricing.ticks') }}" class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">SKU:</label>
                        <input name="sku" value="{{ $selectedSku }}" placeholder="Contoh: PART-BRAKE-01" class="rounded border-gray-300 text-sm">
                        <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-xs text-white">Filter</button>
                    </form>
                    <div class="text-xs text-gray-500">Target skala simulasi: 1 miliar tick/tahun agregat per menit</div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-6 text-center">
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Total Ticks</div>
                        <div class="text-lg font-bold text-gray-800">{{ number_format($analytics['total_ticks'] ?? 0) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Harga Min</div>
                        <div class="text-lg font-bold text-gray-800">Rp {{ number_format($analytics['min_price_idr'] ?? 0) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Harga Rata-rata</div>
                        <div class="text-lg font-bold text-gray-800">Rp {{ number_format($analytics['avg_price_idr'] ?? 0) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Harga Max</div>
                        <div class="text-lg font-bold text-gray-800">Rp {{ number_format($analytics['max_price_idr'] ?? 0) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Pelanggaran Guardrail</div>
                        <div class="text-lg font-bold text-emerald-600">{{ $analytics['guardrail_violations'] ?? 0 }} (Nol)</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">Quote Terkonversi</div>
                        <div class="text-lg font-bold text-indigo-600">{{ $analytics['quotes_converted_count'] ?? 0 }} / {{ ($analytics['quotes_locked_count'] ?? 0) + ($analytics['quotes_converted_count'] ?? 0) }}</div>
                    </div>
                </div>
            </section>

            <!-- Actions: Ingest Tick, Freeze Quote, Convert Quote -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Ingest Price Tick -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">81.1 Ingest Price Tick</h3>
                    <form method="POST" action="{{ route('pricing.ticks.store') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600">SKU</label>
                            <input name="sku" value="{{ $selectedSku }}" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Harga Tick (IDR)</label>
                            <input name="price_idr" type="number" min="1000" value="125000" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Demand Index</label>
                            <input name="demand_index" type="number" step="0.05" min="0.1" max="5.0" value="1.25" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Sumber Penggerak</label>
                            <select name="driver_source" class="w-full rounded border-gray-300 text-sm">
                                <option value="DEMAND_SURGE">DEMAND_SURGE</option>
                                <option value="INVENTORY_SCARCITY">INVENTORY_SCARCITY</option>
                                <option value="COMMODITY_FEED">COMMODITY_FEED</option>
                                <option value="OFF_PEAK">OFF_PEAK</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded bg-indigo-600 py-2 text-xs font-semibold text-white">Catat Tick Idempoten</button>
                    </form>
                </section>

                <!-- Freeze Price Quote -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">81.4 Freeze Price Quote</h3>
                    <form method="POST" action="{{ route('pricing.quote.freeze') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600">SKU</label>
                            <input name="sku" value="{{ $selectedSku }}" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Base HPP (IDR)</label>
                                <input name="base_hpp_idr" type="number" min="1000" value="100000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">HET Ceiling (IDR)</label>
                                <input name="het_ceiling_idr" type="number" min="1000" value="180000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Demand Index</label>
                                <input name="demand_index" type="number" step="0.05" min="0.1" max="5.0" value="1.40" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Kanal</label>
                                <select name="channel" class="w-full rounded border-gray-300 text-sm">
                                    <option value="STORE_B2C">Store B2C</option>
                                    <option value="DISTRIBUTOR_WHOLESALE">Distributor Wholesale</option>
                                    <option value="B2B_MARKETPLACE">B2B Marketplace</option>
                                    <option value="EXPORT">Export</option>
                                    <option value="VENDING">Vending</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Harga Kontrak (Opsional)</label>
                                <input name="contract_price_idr" type="number" min="0" placeholder="0" class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Kunci (Menit)</label>
                                <input name="lock_minutes" type="number" min="1" max="1440" value="30" class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-xs font-semibold text-white">Bekukan Quote (Timelock)</button>
                    </form>
                </section>

                <!-- Convert Quote to Order -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">81.4 Convert Quote to Order</h3>
                    <form method="POST" action="{{ route('pricing.quote.convert') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600">Kode Quote (Timelock)</label>
                            <input name="quote_code" placeholder="QT-XXXXXXXXXXXX" required class="w-full rounded border-gray-300 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Referensi Dokumen Order</label>
                            <input name="order_ref" placeholder="ORD-20261019-001" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <p class="text-xs text-gray-500">
                            Konversi memvalidasi integritas quote hash kriptografis dan memastikan harga tidak berubah meskipun tick bergerak.
                        </p>
                        <button type="submit" class="w-full rounded bg-purple-600 py-2 text-xs font-semibold text-white">Kunci ke Order (Immutable)</button>
                    </form>
                </section>
            </div>

            <!-- Frozen Quotes Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Frozen Quotes (Immutable Timelock)</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode Quote</th>
                                <th class="p-2">SKU</th>
                                <th class="p-2">Kanal</th>
                                <th class="p-2">Harga Beku</th>
                                <th class="p-2">Floor - Ceiling</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Order Ref</th>
                                <th class="p-2">Terkunci Hingga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($quotes as $quote)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $quote->quote_code }}</td>
                                    <td class="p-2">{{ $quote->sku }}</td>
                                    <td class="p-2">{{ $quote->channel }}</td>
                                    <td class="p-2 font-semibold">Rp {{ number_format($quote->frozen_price_idr) }}</td>
                                    <td class="p-2 text-gray-500">Rp {{ number_format($quote->floor_price_idr) }} - {{ number_format($quote->ceiling_price_idr) }}</td>
                                    <td class="p-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $quote->status === 'CONVERTED' ? 'bg-purple-100 text-purple-800' : ($quote->status === 'LOCKED' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600') }}">
                                            {{ $quote->status }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-mono">{{ $quote->order_ref ?? '—' }}</td>
                                    <td class="p-2 text-gray-500">{{ $quote->locked_until?->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-4 text-center text-gray-400">Belum ada quote dibekukan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Minute Aggregates & Recent Ticks -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Minute Aggregates -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">81.1 Agregat per Menit (Candle Sim)</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="p-2">Menit</th>
                                    <th class="p-2">Open</th>
                                    <th class="p-2">High</th>
                                    <th class="p-2">Low</th>
                                    <th class="p-2">Close</th>
                                    <th class="p-2">Ticks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-gray-700">
                                @forelse ($aggregates as $agg)
                                    <tr>
                                        <td class="p-2 font-mono">{{ $agg->bucket_minute?->format('H:i') }}</td>
                                        <td class="p-2">Rp {{ number_format($agg->open_price_idr) }}</td>
                                        <td class="p-2 text-emerald-600">Rp {{ number_format($agg->high_price_idr) }}</td>
                                        <td class="p-2 text-rose-600">Rp {{ number_format($agg->low_price_idr) }}</td>
                                        <td class="p-2 font-semibold">Rp {{ number_format($agg->close_price_idr) }}</td>
                                        <td class="p-2">{{ $agg->tick_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="p-4 text-center text-gray-400">Belum ada agregat tick.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- High-frequency Price Ticks -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">Riwayat Price Ticks Terakhir</h3>
                    <div class="mt-3 overflow-x-auto max-h-96">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 sticky top-0">
                                <tr>
                                    <th class="p-2">Waktu</th>
                                    <th class="p-2">Harga</th>
                                    <th class="p-2">Demand</th>
                                    <th class="p-2">Penggerak</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-gray-700">
                                @forelse ($ticks as $t)
                                    <tr>
                                        <td class="p-2 font-mono">{{ $t->tick_time?->format('H:i:s') }}</td>
                                        <td class="p-2 font-semibold">Rp {{ number_format($t->price_idr) }}</td>
                                        <td class="p-2">{{ number_format((float) $t->demand_index, 2) }}x</td>
                                        <td class="p-2"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700 text-xs">{{ $t->driver_source }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="p-4 text-center text-gray-400">Belum ada price tick.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
