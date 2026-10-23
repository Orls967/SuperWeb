@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('payment.inspector.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm font-medium">
            &larr; Back to Payment Intent Inspector
        </a>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            Payment Intent #{{ Str::limit($intent->uuid, 16) }}
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">Cryptographically verifiable payment transaction record</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm mb-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Intent Parameters</h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Full UUID</dt>
                <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-white break-all">{{ $intent->uuid }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                        @if($intent->status->value === 'captured') bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200
                        @elseif($intent->status->value === 'held') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                        @elseif($intent->status->value === 'failed') bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200
                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @endif">
                        {{ $intent->status->value }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Authorized Principal Amount</dt>
                <dd class="mt-1 text-xl font-extrabold text-indigo-600 dark:text-indigo-400">
                    {{ number_format((int)$intent->amount) }} {{ $intent->currency }}
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Refunded Amount</dt>
                <dd class="mt-1 font-semibold text-rose-600">
                    {{ number_format((int)$intent->refunded_amount) }} {{ $intent->currency }}
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Payer User</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $intent->payer?->name ?? 'User #'.$intent->payer_id }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Payable Entity</dt>
                <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-white">{{ $intent->payable_type }} (ID: {{ $intent->payable_id }})</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Idempotency Key</dt>
                <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-white break-all">{{ $intent->idempotency_key }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Ledger Hold Transaction ID</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $intent->hold_transaction_id ?? 'None' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Ledger Capture Transaction ID</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-white">{{ $intent->capture_transaction_id ?? 'None' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Expiration Timestamp</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $intent->expires_at?->toIso8601String() ?? 'N/A' }}</dd>
            </div>
        </dl>

        @if($intent->failure_reason)
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <dt class="font-medium text-rose-600 dark:text-rose-400 mb-1">Failure Reason</dt>
                <dd class="text-rose-800 dark:text-rose-200 bg-rose-50 dark:bg-rose-950/40 p-3 rounded-lg text-sm font-mono">
                    {{ $intent->failure_reason }}
                </dd>
            </div>
        @endif
    </div>
</div>
@endsection
