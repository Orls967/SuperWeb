@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 min-h-screen text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Header -->
        <div class="p-6 bg-gray-800/80 backdrop-blur rounded-2xl shadow-xl border border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span class="p-2 bg-indigo-500/20 text-indigo-400 rounded-lg">📦</span>
                        E-Pharmacy, FEFO Inventory & Chronic Subscriptions (Fase 104)
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">First-Expired-First-Out (FEFO) Inventory, Langganan Obat Pasien Kronis & Smart Multi-Branch Routing</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.telemedicine.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm font-medium transition">
                        ← Dashboard Telemedicine
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/60 border border-emerald-500/50 rounded-xl text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Multi-Branch Pharmacy Routing Dispatch Form -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>🚚</span> Multi-Branch Pharmacy Routing
                </h2>
                <p class="text-xs text-gray-400">Algoritma pemilihan cabang farmasi terdekat dengan ketersediaan stok FEFO lot aktif.</p>

                <form method="POST" action="{{ route('hospital.epharmacy.routing.dispatch') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Kota / Domisili Pasien</label>
                        <input type="text" name="city" value="Jakarta" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Kode Obat (FEFO Stock Item)</label>
                        <input type="text" name="drug_code" value="METFORMIN_500" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-300 mb-1">Jumlah Dibutuhkan (Unit)</label>
                        <input type="number" name="quantity" value="60" min="1" required class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition shadow-lg">
                        Dispatch Rute Cabang Otomatis
                    </button>
                </form>
            </div>

            <!-- Langganan Obat Pasien Kronis -->
            <div class="lg:col-span-2 p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span>🔁</span> Langganan Obat Kronis Pasien
                    </h2>
                    <span class="text-xs text-gray-400">{{ count($subscriptions) }} langganan aktif</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                            <tr>
                                <th class="p-3">Kode Langganan</th>
                                <th class="p-3">Pasien</th>
                                <th class="p-3">Obat & Qty Bulanan</th>
                                <th class="p-3">Refill Berikutnya</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($subscriptions as $sub)
                                <tr class="hover:bg-gray-700/40">
                                    <td class="p-3 font-mono font-medium text-indigo-400">{{ $sub->subscription_code }}</td>
                                    <td class="p-3">{{ $sub->patient->name ?? 'Pasien #' . $sub->patient_id }}</td>
                                    <td class="p-3 font-medium text-white">{{ $sub->drug_code }} ({{ $sub->monthly_quantity }} unit)</td>
                                    <td class="p-3 text-xs text-gray-400">{{ $sub->next_refill_date ? $sub->next_refill_date->format('d M Y') : 'N/A' }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $sub->status === 'ACTIVE' ? 'bg-emerald-950 text-emerald-300 border border-emerald-700' : 'bg-gray-800 text-gray-400' }}">
                                            {{ $sub->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-gray-500">Belum ada data langganan obat kronis.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Stok Lot FEFO Farmasi -->
        <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span>📊</span> Manajemen Batch / Lot Stok FEFO Farmasi
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Nomor Lot Batch</th>
                            <th class="p-3">Cabang Farmasi</th>
                            <th class="p-3">Kode Obat</th>
                            <th class="p-3">Stok Tersedia</th>
                            <th class="p-3">Tanggal Kedaluwarsa</th>
                            <th class="p-3">Karantina / Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @forelse($lots as $lot)
                            <tr class="hover:bg-gray-700/40">
                                <td class="p-3 font-mono font-medium text-emerald-400">{{ $lot->batch_lot_number }}</td>
                                <td class="p-3">{{ $lot->branch->name ?? 'Cabang #' . $lot->pharmacy_branch_id }}</td>
                                <td class="p-3 text-white">{{ $lot->drug_code }}</td>
                                <td class="p-3 font-semibold">{{ $lot->quantity_available }} unit</td>
                                <td class="p-3 text-xs text-amber-300">{{ $lot->expiry_date ? $lot->expiry_date->format('d M Y') : 'N/A' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-[11px] rounded {{ $lot->is_quarantined ? 'bg-rose-900 text-rose-200' : 'bg-emerald-900 text-emerald-200' }}">
                                        {{ $lot->is_quarantined ? 'TERKARANTINA' : 'AKTIF' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500">Belum ada lot obat FEFO tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
