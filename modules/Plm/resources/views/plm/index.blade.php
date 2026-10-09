@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">R&D & Product Lifecycle Management (PLM)</h2>
            <p class="mt-1 text-sm text-gray-400">Pipeline Inovasi Stage-Gate, Engineering BOM & Riwayat ECO Kriptografis</p>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Daftar Proyek R&D Aktif</h3>
                    <div class="space-y-2">
                        @forelse($projects as $prj)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $prj->name }} ({{ $prj->code }})</div>
                                    <div class="text-xs text-gray-400">Stage: {{ ucfirst($prj->stage) }} • Projected ROI: {{ $prj->projected_roi_percent }}%</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-emerald-400 text-sm">Rp {{ number_format($prj->budget_rd_idr, 0, ',', '.') }}</div>
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-blue-900 text-blue-200">{{ $prj->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada proyek R&D.</div>
                        @endforelse
                    </div>
                </div>

                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Engineering Change Orders (ECO)</h3>
                    <div class="space-y-2">
                        @forelse($changeOrders as $eco)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $eco->title }} ({{ $eco->eco_number }})</div>
                                    <div class="text-xs text-gray-400 font-mono">Hash: {{ substr($eco->hash, 0, 16) }}...</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-yellow-400 text-sm">Dampak: Rp {{ number_format($eco->cost_impact_idr, 0, ',', '.') }}</div>
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-green-900 text-green-200">{{ $eco->status }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada ECO.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
