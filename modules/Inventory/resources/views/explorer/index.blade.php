@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Inventory" />

<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Inventory Stock Movement Explorer</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Audit trail log and analytical inspector for all warehouse inventory operations</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ds.badge-status status="released" />
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                Active Audit Log
            </span>
        </div>
    </div>

    {{-- Summary KPI Cards dengan x-ds.stat --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-ds.stat label="Total Recorded Movements" :value="number_format($totalMovements)" hint="All recorded operations" icon="cube" />
        <x-ds.stat label="Total Stock Inflow (Units)" :value="'+' . number_format($totalInflow)" hint="Barang masuk" change-type="positive" icon="check-circle" />
        <x-ds.stat label="Total Stock Outflow (Units)" :value="'-' . number_format($totalOutflow)" hint="Barang keluar" change-type="negative" icon="chart-bar" />
    </div>

    {{-- Filter Form --}}
    <form method="GET" action="{{ route('inventory.explorer.index') }}" class="flex flex-wrap items-end gap-4 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
        <div class="flex-1 min-w-[200px]">
            <x-ds.select
                name="reason"
                label="Filter Alasan Perpindahan"
                :value="$currentReason"
                :options="array_merge(['' => 'All Movement Reasons'], collect($reasons)->mapWithKeys(fn($r) => [$r->value => ucfirst(str_replace('_', ' ', $r->value))])->toArray())"
            />
        </div>
        <div>
            <x-ds.button type="submit" variant="primary" size="md">Filter Ledger</x-ds.button>
        </div>
    </form>

    {{-- Movements Table --}}
    <x-ds.card>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-semibold uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">ID</th>
                        <th class="px-6 py-3">Product ID</th>
                        <th class="px-6 py-3">Quantity</th>
                        <th class="px-6 py-3">Reason</th>
                        <th class="px-6 py-3">Source Type</th>
                        <th class="px-6 py-3">Timestamp</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-800 dark:text-slate-200">
                    @forelse($movements as $m)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-mono font-medium text-xs">#{{ $m->id }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-blue-600 dark:text-blue-400">PRD-{{ $m->product_id }}</td>
                            <td class="px-6 py-4 font-bold {{ $m->qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $m->qty > 0 ? '+'.$m->qty : $m->qty }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $m->reason->value ?? $m->reason }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-500">{{ $m->source_type ?? 'DIRECT' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $m->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('inventory.explorer.show', $m->id) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                    Inspect &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8">
                                <x-ds.empty
                                    title="No Stock Movements Found"
                                    description="No inventory operations match the selected filter parameters."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $movements->links() }}
            </div>
        @endif
    </x-ds.card>
</div>
@endsection
