@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🧪</span> Mine-to-Port Assay Grade Reconciliation
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Three-point reconciliation between Mine ROM assay, Export Barge Loading assay, and Discharge Port assay.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('mining.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Mining Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Reconcile Lot Assay</h3>
                    <form action="{{ route('mining.operations.grade-reconciliation.reconcile') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Ore Lot Identifier</label>
                            <input type="text" name="lot_code" value="LOT-NICKEL-2026-B1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Mine-Site Lab Assay (% Ni)</label>
                            <input type="number" step="0.01" name="mine_grade_ni_pct" value="1.85" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Discharge Port Independent Surveyor Assay (% Ni)</label>
                            <input type="number" step="0.01" name="port_grade_ni_pct" value="1.81" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Lot Tonnage (Tons)</label>
                            <input type="number" step="0.1" name="lot_tonnage" value="10500" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                            Reconcile Commercial Assay Bias
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Commercial Dispute Thresholds</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&le; 0.10% Ni Variance</span>
                                <p class="text-xs text-gray-400">Within commercial trade tolerance</p>
                            </div>
                            <span class="px-2.5 py-1 bg-emerald-900 text-emerald-300 font-mono font-bold text-xs rounded border border-emerald-700">Accepted</span>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-white text-sm">&gt; 0.10% Ni Variance</span>
                                <p class="text-xs text-gray-400">Triggers umpire laboratory assay dispute &amp; payment hold</p>
                            </div>
                            <span class="px-2.5 py-1 bg-rose-900 text-rose-300 font-mono font-bold text-xs rounded border border-rose-700">Disputed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
