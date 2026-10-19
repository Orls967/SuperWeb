<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cross-Border Crypto Clearing &amp; CBAM Compliance — Fase 83</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-800">
                <strong>Fase 83:</strong> Stablecoin escrow lintas batas terdesentralisasi, kunci kurs FX ber-versi, verifikasi kriptografis Bill of Lading &amp; chain of custody POD logistik, anti-fraud window 24 jam, dan sertifikasi EU CBAM dengan mitigasi kredit karbon ESG.
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-6 text-center">
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total Escrow</div>
                    <div class="text-xl font-bold text-gray-800">{{ number_format($escrowSummary['total_escrows'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Likuiditas Aktif</div>
                    <div class="text-xl font-bold text-emerald-600">Rp {{ number_format($escrowSummary['active_liquidity_idr'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Dalam Dispute Hold</div>
                    <div class="text-xl font-bold text-amber-600">{{ number_format($escrowSummary['disputed_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Settlement Success</div>
                    <div class="text-xl font-bold text-teal-600">{{ $escrowSummary['settlement_success_rate'] ?? 100 }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total Emisi CBAM</div>
                    <div class="text-xl font-bold text-gray-800">{{ number_format($cbamSummary['total_emissions_tco2'] ?? 0, 1) }} tCO2e</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Kewajiban Bea CBAM</div>
                    <div class="text-xl font-bold text-rose-600">Rp {{ number_format($cbamSummary['total_levy_liability_idr'] ?? 0) }}</div>
                </div>
            </div>

            <!-- Interactive Forms -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Stablecoin Escrow Deposit -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">83.1 Deposit Stablecoin Escrow (USDC)</h3>
                    <form method="POST" action="{{ route('trade_finance.crossborder.deposit') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Importer ID</label>
                                <input name="importer_id" type="number" min="1" value="101" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Exporter ID</label>
                                <input name="exporter_id" type="number" min="1" value="202" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Nilai Deposit (Cents USDC)</label>
                                <input name="amount_cents" type="number" min="100" value="500000" required class="w-full rounded border-gray-300 text-sm">
                                <span class="text-[10px] text-gray-400">500,000 cents = $5,000 USD</span>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Kurs Terkunci (IDR/USD)</label>
                                <input name="locked_fx_rate_idr" type="number" min="1000" value="16000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Nomor Bill of Lading (BL)</label>
                                <input name="bl_number" value="BL-MSCU-{{ time() }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">BL Document Payload / URI</label>
                                <input name="bl_document" value="DOC_BL_VERIFIED_EXPORT_2026" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded bg-teal-600 py-2 text-xs font-semibold text-white">Deposit &amp; Kunci Kurs Escrow</button>
                    </form>
                </section>

                <!-- CBAM Emission Certificate -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">83.3 Penerbitan Dokumen Sertifikasi CBAM</h3>
                    <form method="POST" action="{{ route('trade_finance.crossborder.cbam') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Nomor Kontainer</label>
                                <input name="container_number" value="CONT-EU-{{ time() }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Kode Pabrik Asal</label>
                                <input name="origin_factory_code" value="PLANT-CILEGON-01" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Komoditas</label>
                                <select name="commodity_code" class="w-full rounded border-gray-300 text-sm">
                                    <option value="STEEL">Baja (Steel)</option>
                                    <option value="ALUMINUM">Aluminium</option>
                                    <option value="CEMENT">Semen</option>
                                    <option value="FERTILIZER">Pupuk</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Massa Bersih (Ton)</label>
                                <input name="net_mass_tons" type="number" step="0.1" min="0.1" value="25.0" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Emisi (tCO2e)</label>
                                <input name="embedded_emissions_tco2" type="number" step="0.1" min="0.1" value="38.5" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Tautan Kredit Karbon ESG (Opsional)</label>
                            <input name="carbon_credit_ref" placeholder="ESG-CR-2026-CARBON-OFF-01" class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-xs font-semibold text-white">Hitung Bea &amp; Terbitkan Sertifikat CBAM</button>
                    </form>
                </section>
            </div>

            <!-- Escrows Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Kontrak Escrow Kripto Lintas Batas</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode Escrow</th>
                                <th class="p-2">BL / Kontainer</th>
                                <th class="p-2">Pihak</th>
                                <th class="p-2">Nilai Stablecoin</th>
                                <th class="p-2">Kurs FX Terkunci</th>
                                <th class="p-2">Nilai Setara IDR</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($escrows as $escrow)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $escrow->escrow_code }}</td>
                                    <td class="p-2 font-mono">{{ $escrow->bl_number }}</td>
                                    <td class="p-2">Imp #{{ $escrow->importer_id }} &rarr; Exp #{{ $escrow->exporter_id }}</td>
                                    <td class="p-2 font-semibold">${{ number_format($escrow->amount_cents / 100, 2) }} USDC</td>
                                    <td class="p-2 text-gray-500">Rp {{ number_format($escrow->locked_fx_rate_idr) }}</td>
                                    <td class="p-2 font-bold text-teal-700">Rp {{ number_format(($escrow->amount_cents / 100) * $escrow->locked_fx_rate_idr) }}</td>
                                    <td class="p-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $escrow->status === 'RELEASED' ? 'bg-emerald-100 text-emerald-800' : ($escrow->status === 'DISPUTED' ? 'bg-rose-100 text-rose-800' : 'bg-blue-100 text-blue-800') }}">
                                            {{ $escrow->status }}
                                        </span>
                                    </td>
                                    <td class="p-2 flex gap-1">
                                        @if ($escrow->status === 'DEPOSITED')
                                            <!-- Release with POD chain hash -->
                                            <form method="POST" action="{{ route('trade_finance.crossborder.release', $escrow) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="pod_hash" value="POD_CHAIN_HASH_{{ $escrow->id }}_{{ time() }}">
                                                <button class="rounded bg-teal-600 px-2 py-1 text-xs text-white">Rilis POD</button>
                                            </form>
                                            <!-- Dispute button -->
                                            <form method="POST" action="{{ route('trade_finance.crossborder.dispute', $escrow) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="reason" value="Kargo tidak sesuai spesifikasi BL">
                                                <button class="rounded bg-rose-600 px-2 py-1 text-xs text-white">Dispute</button>
                                            </form>
                                        @elseif ($escrow->status === 'DISPUTED')
                                            <!-- Resolve Dispute: Refund or Proceed -->
                                            <form method="POST" action="{{ route('trade_finance.crossborder.resolve-dispute', $escrow) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="REFUND">
                                                <button class="rounded bg-amber-600 px-2 py-1 text-xs text-white">Refund</button>
                                            </form>
                                            <form method="POST" action="{{ route('trade_finance.crossborder.resolve-dispute', $escrow) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="PROCEED">
                                                <button class="rounded bg-blue-600 px-2 py-1 text-xs text-white">Buka Hold</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-4 text-center text-gray-400">Belum ada transaksi escrow cross-border.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- CBAM Certificates Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Sertifikat Emisi Karbon CBAM (Ekspor UE)</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode Sertifikat</th>
                                <th class="p-2">Kontainer</th>
                                <th class="p-2">Pabrik</th>
                                <th class="p-2">Komoditas</th>
                                <th class="p-2">Massa Bersih</th>
                                <th class="p-2">Emisi tCO2e</th>
                                <th class="p-2">Biaya Bea CBAM</th>
                                <th class="p-2">Kredit Karbon Terhubung</th>
                                <th class="p-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($cbamCertificates as $cert)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $cert->cert_code }}</td>
                                    <td class="p-2 font-mono">{{ $cert->container_number }}</td>
                                    <td class="p-2">{{ $cert->origin_factory_code }}</td>
                                    <td class="p-2 font-semibold">{{ $cert->commodity_code }}</td>
                                    <td class="p-2">{{ number_format((float) $cert->net_mass_tons, 1) }} Ton</td>
                                    <td class="p-2 font-bold text-gray-800">{{ number_format((float) $cert->embedded_emissions_tco2, 2) }}</td>
                                    <td class="p-2 font-bold text-rose-700">Rp {{ number_format($cert->cbam_levy_cost_idr) }}</td>
                                    <td class="p-2">
                                        @if ($cert->carbon_credit_ref)
                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">{{ $cert->carbon_credit_ref }}</span>
                                        @else
                                            <span class="text-gray-400">Tidak ada</span>
                                        @endif
                                    </td>
                                    <td class="p-2">
                                        <span class="rounded bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-800">{{ $cert->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-400">Belum ada sertifikat CBAM diterbitkan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
