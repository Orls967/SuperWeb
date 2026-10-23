@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Payment Intent Inspector</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Real-time payment gateway intents, escrow holds, and ledger capture transactions</p>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                Payment Hub Live
            </span>
        </div>
    </div>

    <!-- KPI Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Payment Intents</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalIntents) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Captured Settlements</div>
            <div class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ number_format($capturedCount) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Escrow Holds</div>
            <div class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">{{ number_format($heldCount) }}</div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 mb-6">
        <form method="GET" action="{{ route('payment.inspector.index') }}" class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="status" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Status Filter</label>
                <select name="status" id="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">All Intent Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" {{ $currentStatus === $st->value ? 'selected' : '' }}>
                            {{ $st->label() }} ({{ $st->value }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label for="currency" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Currency</label>
                <input type="text" name="currency" id="currency" value="{{ $currentCurrency }}" placeholder="e.g. IDR, USDC" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm uppercase">
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Payment Intents Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 font-semibold uppercase text-xs">
                <tr>
                    <th class="px-6 py-3 text-left">UUID</th>
                    <th class="px-6 py-3 text-left">Payer</th>
                    <th class="px-6 py-3 text-left">Payable Target</th>
                    <th class="px-6 py-3 text-left">Amount</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Created At</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                @forelse($intents as $intent)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition">
                        <td class="px-6 py-4 font-mono text-xs">{{ Str::limit($intent->uuid, 13) }}</td>
                        <td class="px-6 py-4">{{ $intent->payer?->name ?? 'User #'.$intent->payer_id }}</td>
                        <td class="px-6 py-4 text-xs font-mono text-gray-500">{{ class_basename($intent->payable_type) }} #{{ $intent->payable_id }}</td>
                        <td class="px-6 py-4 font-bold">{{ number_format((int)$intent->amount) }} {{ $intent->currency }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                @if($intent->status->value === 'captured') bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200
                                @elseif($intent->status->value === 'held') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                @elseif($intent->status->value === 'failed') bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200
                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @endif">
                                {{ $intent->status->value }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ $intent->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('payment.inspector.show', $intent->uuid) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Inspect</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No payment intents found matching criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $intents->links() }}
        </div>
    </div>
</div>
@endsection
