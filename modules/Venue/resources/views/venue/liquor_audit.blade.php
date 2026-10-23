@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🥃</span> Liquor Pour Spout IoT Flow Telemetry & Shrinkage Reconciliation
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Smart spout ultrasonic metering vs POS billed shot ledger variance tracking.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.tables.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        VIP Tables
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-4 bg-rose-900/60 border border-rose-500 rounded-lg text-rose-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Reconcile Spout Volume</h3>
                    <form action="{{ route('venue.tables.liquor-audit.reconcile') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Liquor SKU</label>
                            <input type="text" name="bottle_sku" value="TEQUILA-CLASE-AZUL-REPOSADO" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">POS Billed Shots (45ml each)</label>
                            <input type="number" name="billed_shots" value="20" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">IoT Spout Metered Volume (ml)</label>
                            <input type="number" step="0.1" name="metered_dispensed_ml" value="990.0" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Reconcile Spout Telemetry
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Shrinkage Variance Benchmarks</h3>
                    <ul class="text-xs text-gray-300 space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">&le; 8.0%:</span>
                            <span>Standard pour tolerance (ice splash, nozzle cling). Ledger remains balanced.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-rose-400 font-bold">&gt; 8.0%:</span>
                            <span>Shrinkage alert triggered! Discrepancy logged for bar inventory loss audit.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
