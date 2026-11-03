@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Microgrid Battery Peak Shaving & Islanding Resilience</h2>
            <p class="mt-1 text-sm text-gray-400">Pengendali Baterai Microgrid (BESS Arbitrage), Islanding Pemadaman Darurat & Kliring Energi Surya P2P</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Islanding Resilience Section -->
            <div class="mt-6 p-6 bg-red-950/40 border border-red-700/50 rounded-lg">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-red-200 flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-red-500 animate-pulse"></span>
                            Microgrid Islanding Emergency Control
                        </h3>
                        <p class="text-xs text-red-300/80 mt-1">
                            Sistem leksikografis priority knapsack (Hospital > Factory > Mall) otomatis memangkas beban non-kritis saat jaringan utama terputus.
                        </p>
                    </div>
                    <span class="px-3 py-1.5 bg-red-800/80 text-white text-xs font-semibold rounded uppercase tracking-wider">
                        Islanding Ready
                    </span>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Battery Dispatch -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Trigger BESS Peak Shaving Dispatch</h3>
                    <form action="{{ route('egy.microgrid.dispatch') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Battery Asset ID</label>
                            <input type="text" name="asset_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="BESS-ASSET-01">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Discharge Energy (kWh)</label>
                            <input type="number" name="discharge_kwh" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="500">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                            Dispatch Battery Peak Shaving
                        </button>
                    </form>
                </div>

                <!-- P2P Solar Trade -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Kliring Peer-to-Peer Solar Surplus</h3>
                    <form action="{{ route('egy.microgrid.p2p') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-300 mb-1">Seller (Prosumer)</label>
                                <input type="text" name="seller_party_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="PROSUMER-01">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-300 mb-1">Buyer (Consumer)</label>
                                <input type="text" name="buyer_party_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="CONSUMER-02">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Surplus Energy (kWh)</label>
                            <input type="number" name="surplus_kwh" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="50">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded transition">
                            Selesaikan Transaksi P2P
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
