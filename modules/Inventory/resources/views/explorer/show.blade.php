@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('inventory.explorer.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm font-medium">
            &larr; Back to Movement Explorer
        </a>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            Stock Movement Detail #{{ $movement->id }}
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Append-only immutable warehouse movement entry</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm mb-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Movement Specifications</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Movement ID</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">#{{ $movement->id }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Product Reference ID</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">PRD-{{ $movement->product_id }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Quantity Delta</dt>
                <dd class="mt-1 text-lg font-bold {{ $movement->qty > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $movement->qty > 0 ? '+'.$movement->qty : $movement->qty }} Units
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Reason Code</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                        {{ $movement->reason->value ?? $movement->reason }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Source Entity</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">
                    {{ $movement->source_type ?? 'N/A' }} (ID: {{ $movement->source_id ?? 'N/A' }})
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Reference Movement</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">
                    {{ $movement->reference_movement_id ? '#'.$movement->reference_movement_id : 'None' }}
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Logged At</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $movement->created_at?->toIso8601String() ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Logged By User</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $movement->creator?->name ?? 'System Automated' }}</dd>
            </div>
        </dl>

        @if($movement->note)
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <dt class="font-medium text-gray-500 dark:text-gray-400 mb-1">Audit Notes</dt>
                <dd class="text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg text-sm font-mono">
                    {{ $movement->note }}
                </dd>
            </div>
        @endif
    </div>
</div>
@endsection
