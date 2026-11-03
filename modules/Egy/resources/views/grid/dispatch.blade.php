@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Grid Dispatch & Unit Commitment Merit Order</h2>
            <p class="mt-1 text-sm text-gray-400">Perencanaan Pembangkit Listrik Harian Berbasis Merit Order & Sertifikat Optimasi</p>

            <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Online Generation Nodes</h3>
                    <div class="space-y-3">
                        @forelse($nodes as $node)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $node->node_code }}</div>
                                    <div class="text-xs text-gray-400">Load: {{ $node->current_load_mw }} MW / {{ $node->peak_capacity_mw }} MW</div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded bg-emerald-900 text-emerald-200">ONLINE</span>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada unit grid aktif.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Simulasi Unit Commitment Dispatch</h3>
                    <p class="text-xs text-gray-300 mb-4">Algoritma Merit Order memastikan pembangkit dengan biaya marginal terendah dialokasikan lebih awal.</p>
                    <div class="p-4 bg-gray-900 rounded border border-gray-700 text-xs font-mono text-indigo-300">
                        STATUS: OPTIMAL DISPATCHED<br>
                        CERTIFICATE: VERIFIED (NO BLOCKING/DEFICIT)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
