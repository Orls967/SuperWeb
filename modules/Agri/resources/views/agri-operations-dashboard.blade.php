@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Agricultural Supply Chain Operations</h1>
            <p class="text-gray-600 dark:text-gray-400">Plasma smallholder ecosystem, precision harvest grading, and ledger-integrated farmer payouts.</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('agri.contracts.index') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">Plasma Contracts</a>
            <a href="{{ route('agri.grading.index') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Quality Grading</a>
            <a href="{{ route('agri.payout.index') }}" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg">Harvest Payouts</a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Registered Farmers</div>
            <div class="text-3xl font-bold text-green-600 mt-2">{{ $audit['farmers_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Contracts</div>
            <div class="text-3xl font-bold text-emerald-600 mt-2">{{ $audit['contracts_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Harvest Received</div>
            <div class="text-3xl font-bold text-teal-600 mt-2">{{ number_format($audit['total_harvest_kg'] ?? 0, 1) }} kg</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Farmer Payouts</div>
            <div class="text-3xl font-bold text-lime-600 mt-2">Rp {{ number_format($audit['total_net_payout_idr'] ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Audit Status Card -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Agri Integrity Audit</h3>
                <p class="text-sm text-gray-500 mt-1">Verification of contract advance balances and payout reconciliation.</p>
            </div>
            <span class="px-3 py-1 text-sm font-bold rounded-full {{ ($audit['status'] ?? '') === 'HEALTHY' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $audit['status'] ?? 'UNKNOWN' }}
            </span>
        </div>
    </div>
</div>
@endsection
