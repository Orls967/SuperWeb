@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🍽️</span> Banquet Event Order (BEO) Recipe BOM &amp; Margin Control
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        F&amp;B ingredient costing, AV production rollup, and mandatory 40% gross margin enforcement.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('hotel.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Hotel Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Create Banquet Order</h3>
                    <form action="{{ route('hotel.pms.banquet.create') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">MICE / Wedding Contract Ref</label>
                            <input type="text" name="mice_contract_id" value="MICE-GALA-2026-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Menu Package Name</label>
                            <input type="text" name="package_name" value="Grand Ballroom 5-Course Royal Dinner" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Guaranteed Pax Count</label>
                            <input type="number" name="pax_count" value="250" min="10" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Price per Pax (IDR)</label>
                            <input type="number" name="package_price_per_pax_idr" value="850000" min="50000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">F&amp;B Ingredients BOM (IDR)</label>
                            <input type="number" name="fnb_materials_cost_idr" value="65000000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">AV / Staging Vendor (IDR)</label>
                            <input type="number" name="av_equipment_vendor_cost_idr" value="25000000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Decor / Floral Vendor (IDR)</label>
                            <input type="number" name="decor_florist_vendor_cost_idr" value="15000000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Validate Margin &amp; Submit BEO
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Approved Banquet Production Sheets</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Contract Ref</th>
                                    <th class="p-3">Package</th>
                                    <th class="p-3 text-right">Pax</th>
                                    <th class="p-3 text-right">Production Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($sheets as $s)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $s->mice_contract_id }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $s->menu_package_name }}</td>
                                        <td class="p-3 text-right font-mono">{{ $s->pax_count }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($s->total_production_cost_idr) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-gray-500">No banquet production sheets on file.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
