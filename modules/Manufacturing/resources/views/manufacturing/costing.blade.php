<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Biaya Produksi &amp; Margin</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">38.1 Versi biaya standar</h3>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('manufacturing.production.index') }}" class="text-indigo-700 underline">Shop floor</a>
                        <a href="{{ route('manufacturing.index') }}" class="text-indigo-700 underline">Master data</a>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse ($costVersions as $version)
                        <div class="flex items-center justify-between rounded border p-3 text-sm">
                            <span>{{ $version->name }} v{{ $version->version }} — {{ $version->status }} · {{ $version->standard_costs_count }} item standar</span>
                            <span class="flex gap-2">
                                @if ($version->status === 'draft')
                                    <form method="POST" action="{{ route('manufacturing.costing.versions.submit', $version) }}">@csrf<button class="text-indigo-700 underline">Ajukan</button></form>
                                @elseif ($version->status === 'pending_approval')
                                    <form method="POST" action="{{ route('manufacturing.costing.versions.approve', $version) }}">@csrf<button class="text-green-700 underline">Setujui</button></form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada versi biaya.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">38.7 Margin per barang jadi</h3>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr>
                    <th class="p-2 text-left">Kode</th><th class="p-2 text-left">Material</th>
                    <th class="p-2 text-right">Terjual</th><th class="p-2 text-right">Pendapatan</th>
                    <th class="p-2 text-right">Unit cost</th><th class="p-2 text-right">HPP</th><th class="p-2 text-right">Margin</th>
                </tr></thead><tbody>
                    @forelse ($margins as $row)
                        <tr class="border-t">
                            <td class="p-2">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['material'] }}</td>
                            <td class="p-2 text-right">{{ $row['sold_qty'] }}</td>
                            <td class="p-2 text-right">{{ number_format($row['revenue_idr']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['unit_cost_idr']) }}</td>
                            <td class="p-2 text-right">{{ number_format($row['cogs_idr']) }}</td>
                            <td class="p-2 text-right {{ $row['margin_idr'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($row['margin_idr']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-2 text-gray-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">38.2 Drill-down biaya per order</h3>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr>
                    <th class="p-2 text-left">Order</th><th class="p-2 text-left">Material</th>
                    <th class="p-2 text-right">Bahan</th><th class="p-2 text-right">Tenaga</th>
                    <th class="p-2 text-right">Mesin</th><th class="p-2 text-right">Overhead</th>
                    <th class="p-2 text-right">Subkon</th><th class="p-2 text-right">By-product (−)</th>
                    <th class="p-2 text-right">Total</th><th class="p-2 text-right">Per unit</th>
                </tr></thead><tbody>
                    @forelse ($orderCosts as $cost)
                        <tr class="border-t">
                            <td class="p-2">{{ $cost->order?->number }}</td>
                            <td class="p-2">{{ $cost->order?->material?->code }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->material_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->labor_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->machine_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->overhead_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->subcontract_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->byproduct_credit_idr) }}</td>
                            <td class="p-2 text-right font-semibold">{{ number_format($cost->total_idr) }}</td>
                            <td class="p-2 text-right">{{ number_format($cost->unit_cost_idr) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-2 text-gray-500">Belum ada order yang dihitung biayanya.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </div>
    </div>
</x-app-layout>
