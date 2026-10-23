<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vendor-Managed Inventory (VMI) &amp; C2M Manufacturing — Fase 82</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                <strong>Fase 82:</strong> Integrasi WMS shelf-trigger auto-PO dengan plafon kontrak kerangka, komitmen anggaran double-entry ledger, C2M configurator 3D, validasi anti-cycle BOM, dan routing instruksi SPK pabrik.
            </div>

            <!-- KPI & Analytics Summary -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-6 text-center">
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total VMI Triggers</div>
                    <div class="text-xl font-bold text-gray-800">{{ number_format($vmiSummary['total_replenishments'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Auto-PO Terbit</div>
                    <div class="text-xl font-bold text-emerald-600">{{ number_format($vmiSummary['auto_ordered_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Pending Approval</div>
                    <div class="text-xl font-bold text-amber-600">{{ number_format($vmiSummary['pending_approval_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">VMI Fill Rate</div>
                    <div class="text-xl font-bold text-indigo-600">{{ $vmiSummary['vmi_fill_rate_percent'] ?? 100 }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Pipeline C2M</div>
                    <div class="text-xl font-bold text-purple-600">Rp {{ number_format($c2mBoard['pipeline_value_idr'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Lead Time Rata-rata</div>
                    <div class="text-xl font-bold text-gray-800">{{ $c2mBoard['average_lead_days'] ?? 5 }} Hari</div>
                </div>
            </div>

            <!-- Action Forms -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- VMI Reorder Trigger -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">82.1 Simulasi Titik Pesan Ulang WMS (VMI)</h3>
                    <form method="POST" action="{{ route('manufacturing.vmi.trigger') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Supplier ID</label>
                                <input name="supplier_id" type="number" min="1" value="101" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">SKU Rak</label>
                                <input name="sku" value="PART-VMI-FILTER-01" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Stok Rak Saat Ini</label>
                                <input name="current_stock" type="number" min="0" value="8" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Titik Pesan (ROP)</label>
                                <input name="reorder_point" type="number" min="1" value="10" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Qty Pesan (EOQ)</label>
                                <input name="reorder_qty" type="number" min="1" value="50" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Harga Satuan (IDR)</label>
                                <input name="unit_cost_idr" type="number" min="1000" value="250000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Plafon Kontrak (IDR)</label>
                                <input name="contract_plafond_idr" type="number" min="1000" value="50000000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded bg-indigo-600 py-2 text-xs font-semibold text-white">Evaluasi &amp; Terbitkan Auto-PO</button>
                    </form>
                </section>

                <!-- C2M 3D Configurator -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">82.3 C2M Parametric Configurator</h3>
                    <form method="POST" action="{{ route('manufacturing.c2m.orders.store') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-600">Base Model Komponen</label>
                            <input name="base_model" value="CUSTOM_SPOILER_CF" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Dimensi Panjang (mm)</label>
                                <input name="size_mm" type="number" min="1" max="3000" value="1200" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Material Utama</label>
                                <select name="material" class="w-full rounded border-gray-300 text-sm">
                                    <option value="CARBON_FIBER">Carbon Fiber Prepreg</option>
                                    <option value="AEROSPACE_ALUMINUM">Aerospace Aluminum 7075</option>
                                    <option value="TITANIUM_GR5">Titanium Grade 5</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Finishing Lapisan</label>
                                <select name="finish" class="w-full rounded border-gray-300 text-sm">
                                    <option value="STANDARD">Standard Matte</option>
                                    <option value="TITANIUM_COATED">Titanium Vapor Deposition (+35% Complexity)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Toleransi Mesin</label>
                                <select name="tolerance" class="w-full rounded border-gray-300 text-sm">
                                    <option value="STANDARD">Standard CNC (+-0.1mm)</option>
                                    <option value="AEROSPACE_PRECISION">Aerospace 5-Axis (+-0.01mm, +25% Complexity)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600">Estimasi Biaya Bahan Mentah (IDR)</label>
                            <input name="raw_material_cost_idr" type="number" min="10000" value="1500000" required class="w-full rounded border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-xs font-semibold text-white">Validasi Desain &amp; Hitung Roll-up Harga</button>
                    </form>
                </section>
            </div>

            <!-- VMI Replenishments Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Replenishment VMI (Vendor-Managed)</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode VMI</th>
                                <th class="p-2">Supplier</th>
                                <th class="p-2">SKU</th>
                                <th class="p-2">Stok / ROP</th>
                                <th class="p-2">Qty Pesan</th>
                                <th class="p-2">Estimasi Total</th>
                                <th class="p-2">Plafon Kontrak</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($replenishments as $vmi)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $vmi->vmi_code }}</td>
                                    <td class="p-2">Supplier #{{ $vmi->supplier_id }}</td>
                                    <td class="p-2">{{ $vmi->sku }}</td>
                                    <td class="p-2">{{ $vmi->current_shelf_stock }} / {{ $vmi->reorder_point }}</td>
                                    <td class="p-2 font-semibold">{{ $vmi->suggested_reorder_qty }} pcs</td>
                                    <td class="p-2">Rp {{ number_format($vmi->total_estimated_idr) }}</td>
                                    <td class="p-2 text-gray-500">Rp {{ number_format($vmi->contract_plafond_idr) }}</td>
                                    <td class="p-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $vmi->status === 'AUTO_ORDERED' ? 'bg-emerald-100 text-emerald-800' : ($vmi->status === 'APPROVED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ $vmi->status }}
                                        </span>
                                    </td>
                                    <td class="p-2">
                                        @if ($vmi->status === 'PENDING_APPROVAL')
                                            <form method="POST" action="{{ route('manufacturing.vmi.approve', $vmi) }}" class="inline">
                                                @csrf
                                                <button class="rounded bg-indigo-600 px-2 py-1 text-xs text-white">Setujui</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">Komitmen aktif</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-400">Belum ada replenishment VMI.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- C2M Production Board -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Papan Produksi C2M (Consumer-to-Manufacturer)</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode C2M</th>
                                <th class="p-2">Model</th>
                                <th class="p-2">BOM Cost</th>
                                <th class="p-2">Complexity</th>
                                <th class="p-2">Harga Final</th>
                                <th class="p-2">Lead Time</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">No SPK</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($customOrders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $order->c2m_code }}</td>
                                    <td class="p-2">{{ $order->base_model }}</td>
                                    <td class="p-2">Rp {{ number_format($order->rolled_up_bom_cost_idr) }}</td>
                                    <td class="p-2 font-semibold">{{ number_format((float) $order->complexity_factor, 2) }}x</td>
                                    <td class="p-2 font-bold text-emerald-700">Rp {{ number_format($order->final_price_idr) }}</td>
                                    <td class="p-2">{{ $order->estimated_lead_days }} Hari</td>
                                    <td class="p-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ str_contains($order->status, 'SPK') ? 'bg-purple-100 text-purple-800' : (str_contains($order->status, 'CANCEL') ? 'bg-rose-100 text-rose-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ $order->status }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-mono">{{ $order->spk_number ?? '—' }}</td>
                                    <td class="p-2 flex gap-1">
                                        @if ($order->status === 'DESIGN_VALIDATED')
                                            <form method="POST" action="{{ route('manufacturing.c2m.orders.spk', $order) }}">
                                                @csrf
                                                <button class="rounded bg-purple-600 px-2 py-1 text-xs text-white">Terbitkan SPK</button>
                                            </form>
                                        @endif
                                        @if (! str_contains($order->status, 'CANCEL'))
                                            <form method="POST" action="{{ route('manufacturing.c2m.orders.cancel', $order) }}">
                                                @csrf
                                                <button class="rounded bg-rose-600 px-2 py-1 text-xs text-white">Batal</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="p-4 text-center text-gray-400">Belum ada pesanan C2M.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
