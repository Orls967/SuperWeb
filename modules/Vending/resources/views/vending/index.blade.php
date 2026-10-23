@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Vending" />

<div class="py-12 bg-gray-900 text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🤖</span> AI Demand & Waste Forecasting & Smart Vending
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Holt-Winters Algorithmic Demand Prediction, Automated Agri Purchase Orders, and Unmanned Kiosks
                    </p>
                </div>
                <div class="flex gap-2">
                    <span class="px-3 py-1 bg-cyan-950 text-cyan-400 border border-cyan-700 text-xs font-semibold rounded-full">
                        MAPE Target: &le; 15%
                    </span>
                    <span class="px-3 py-1 bg-emerald-950 text-emerald-400 border border-emerald-700 text-xs font-semibold rounded-full">
                        Daily Ceiling: Rp 15,000,000
                    </span>
                </div>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mt-4 p-4 bg-red-900/60 border border-red-500 rounded-lg text-red-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Smart Vending Units -->
                <div class="bg-gray-800/80 p-5 rounded-lg border border-gray-700 shadow">
                    <h3 class="text-lg font-semibold text-gray-200 mb-4 flex items-center justify-between">
                        <span>Unit Smart Vending Aktif</span>
                        <span class="text-xs text-gray-400 font-normal">{{ $units->count() }} unit terhubung</span>
                    </h3>
                    <div class="space-y-4">
                        @forelse($units as $unit)
                            <div class="p-4 bg-gray-900/90 rounded-lg border border-gray-700">
                                <div class="flex justify-between items-center">
                                    <span class="text-white font-semibold">{{ $unit->location_name }}</span>
                                    <span class="px-2 py-0.5 text-xs font-bold rounded {{ $unit->status === 'ACTIVE' ? 'bg-emerald-900 text-emerald-300 border border-emerald-600' : 'bg-amber-900 text-amber-300 border border-amber-600' }}">
                                        {{ $unit->status }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-400 grid grid-cols-2 gap-2">
                                    <div>Kode: <strong class="text-gray-300">{{ $unit->unit_code }}</strong></div>
                                    <div>Stok: <strong class="{{ $unit->current_stock <= $unit->critical_threshold ? 'text-red-400 font-bold' : 'text-emerald-400' }}">{{ $unit->current_stock }}</strong> / {{ $unit->max_capacity }}</div>
                                    <div>Total Omzet: <strong class="text-amber-400">Rp {{ number_format($unit->total_sales_idr) }}</strong></div>
                                    <div>Terjual: <strong class="text-cyan-400">{{ $unit->total_sales_count }} item</strong></div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-gray-800">
                                    <form action="{{ route('vending.units.dispense', $unit) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="item_code" value="SNK-001">
                                        <input type="hidden" name="qty" value="1">
                                        <input type="hidden" name="price_idr" value="15000">
                                        <select name="payment_method" class="text-xs bg-gray-950 border border-gray-700 rounded px-2 py-1 text-gray-200">
                                            <option value="QRIS">QRIS</option>
                                            <option value="BIOMETRIC_FACE">Face Recognition</option>
                                        </select>
                                        <button type="submit" class="px-3 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs font-semibold transition">
                                            Test Dispense (Rp 15.000)
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada unit vending terdaftar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Auto-PO & Demand Forecasts -->
                <div class="space-y-6">
                    <!-- Demand & Waste Forecasts -->
                    <div class="bg-gray-800/80 p-5 rounded-lg border border-gray-700 shadow">
                        <h3 class="text-lg font-semibold text-gray-200 mb-3">AI Demand & Waste Forecasts</h3>
                        <div class="space-y-3">
                            @forelse($forecasts as $fc)
                                <div class="p-3 bg-gray-900/90 rounded border border-gray-700 text-xs">
                                    <div class="flex justify-between font-semibold">
                                        <span class="text-cyan-400">{{ $fc->forecast_code }}</span>
                                        <span class="text-gray-300">{{ $fc->outlet_code }} ({{ $fc->item_code }})</span>
                                    </div>
                                    <div class="mt-1 text-gray-400 flex justify-between">
                                        <span>Forecast: <strong>{{ $fc->forecast_qty }}</strong> unit</span>
                                        <span>Waste Est: <strong>{{ $fc->waste_forecast_qty }}</strong> unit</span>
                                        <span>MAPE: <strong class="text-emerald-400">{{ $fc->mape_percent ?? 'N/A' }}%</strong></span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-400 text-xs">Belum ada forecast tersimpan.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Auto-PO Queue -->
                    <div class="bg-gray-800/80 p-5 rounded-lg border border-gray-700 shadow">
                        <h3 class="text-lg font-semibold text-gray-200 mb-3">Antrean Auto-PO Bahan Segar</h3>
                        <div class="space-y-3">
                            @forelse($autoPos as $po)
                                <div class="p-3 bg-gray-900/90 rounded border border-gray-700 text-xs">
                                    <div class="flex justify-between font-semibold">
                                        <span class="text-amber-400">{{ $po->auto_po_code }}</span>
                                        <span class="px-2 py-0.5 rounded {{ $po->status === 'AUTO_APPROVED' ? 'bg-emerald-900 text-emerald-300' : 'bg-red-900 text-red-300' }}">
                                            {{ $po->status }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-gray-400 flex justify-between">
                                        <span>Qty: {{ $po->qty_ordered }}</span>
                                        <span>Total: Rp {{ number_format($po->estimated_total_idr) }}</span>
                                        <span>Plafon: Rp {{ number_format($po->daily_plafon_idr) }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-gray-400 text-xs">Belum ada antrean Auto-PO.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
