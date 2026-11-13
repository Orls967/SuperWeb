<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Perencanaan ROI Automasi Gudang (J1)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-6">
            <x-ds.card title="Portofolio Proposal ROI Automasi (J3 — asumsi_simulasi)">
                <div class="mb-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm tabular-nums">
                    <div class="p-2 rounded border border-gray-200 bg-white">
                        <p class="text-xs text-gray-500">Proposal</p>
                        <p class="font-semibold text-gray-900">{{ number_format((int) ($portfolio['total'] ?? 0)) }}</p>
                    </div>
                    <div class="p-2 rounded border border-gray-200 bg-white">
                        <p class="text-xs text-gray-500">Disetujui</p>
                        <p class="font-semibold text-gray-900">{{ number_format((int) ($portfolio['approved_count'] ?? 0)) }}</p>
                    </div>
                    <div class="p-2 rounded border border-gray-200 bg-white">
                        <p class="text-xs text-gray-500">Total CAPEX</p>
                        <p class="font-semibold text-gray-900">{{ $portfolio['total_capex_idr'] ?? 'Rp 0,00' }}</p>
                    </div>
                    <div class="p-2 rounded border border-gray-200 bg-white">
                        <p class="text-xs text-gray-500">Total NPV</p>
                        <p class="font-semibold text-gray-900">{{ $portfolio['total_npv_idr'] ?? 'Rp 0,00' }}</p>
                    </div>
                </div>
            </x-ds.card>
            <x-ds.card title="Rencana ROI &amp; Tahap Automasi">
                <div class="space-y-2">
                    @forelse($plans as $plan)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 bg-white text-sm">
                            <div>
                                <p class="font-semibold text-gray-900">Gudang {{ $plan->warehouse_id }} · {{ $plan->pick_method }}</p>
                                <p class="text-xs text-gray-600">Capex Rp {{ number_format($plan->capex_idr) }} · Target {{ $plan->target_lines_per_hour }} LPH</p>
                            </div>
                            <div class="text-right">
                                <x-ds.badge variant="success">ROI {{ $plan->roi_bps }} bps</x-ds.badge>
                                <p class="text-xs text-gray-600 mt-1">Payback {{ $plan->payback_months }} bulan ({{ $plan->phase_stage }})</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada rencana ROI automasi.</p>
                    @endforelse
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

