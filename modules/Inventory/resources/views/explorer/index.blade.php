@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Inventory" />

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Inventory Stock Movement Explorer</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Audit trail log and analytical inspector for all warehouse inventory operations</p>
        </div>
        <div class="flex space-x-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                Active Audit Log
            </span>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Recorded Movements</div>
            <div class="mt-2 text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ number_format($totalMovements) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Stock Inflow (Units)</div>
            <div class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">+{{ number_format($totalInflow) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Stock Outflow (Units)</div>
            <div class="mt-2 text-3xl font-extrabold text-rose-600 dark:text-rose-400">-{{ number_format($totalOutflow) }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 mb-6">
        <form method="GET" action="{{ route('inventory.explorer.index') }}" class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="reason" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Reason Filter</label>
                <select name="reason" id="reason" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Movement Reasons</option>
                    @foreach($reasons as $reason)
                        <option value="{{ $reason->value }}" {{ $currentReason === $reason->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $reason->value)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition">
                    Filter Ledger
                </button>
            </div>
        </form>
    </div>

    <!-- Movements Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 font-semibold uppercase text-xs">
                <tr>
                    <th class="px-6 py-3 text-left">ID</th>
                    <th class="px-6 py-3 text-left">Product ID</th>
                    <th class="px-6 py-3 text-left">Quantity</th>
                    <th class="px-6 py-3 text-left">Reason</th>
                    <th class="px-6 py-3 text-left">Source Type</th>
                    <th class="px-6 py-3 text-left">Timestamp</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                @forelse($movements as $m)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                        <td class="px-6 py-4 font-mono font-medium">#{{ $m->id }}</td>
                        <td class="px-6 py-4">PRD-{{ $m->product_id }}</td>
                        <td class="px-6 py-4 font-bold {{ $m->qty > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $m->qty > 0 ? '+'.$m->qty : $m->qty }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                {{ $m->reason->value ?? $m->reason }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-500">{{ $m->source_type ?? 'DIRECT' }}</td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ $m->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('inventory.explorer.show', $m->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Inspect</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No stock movements found matching filter parameters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $movements->links() }}
        </div>
    </div>
</div>
@endsection
