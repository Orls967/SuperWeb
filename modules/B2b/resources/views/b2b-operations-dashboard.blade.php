@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">B2B Commerce & Auction Control Center</h1>
            <p class="text-gray-600 dark:text-gray-400">Wholesale marketplace, surplus Dutch auction mechanisms, and multi-party escrow clearing.</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('b2b.wholesale.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">Wholesale Catalog</a>
            <a href="{{ route('b2b.auction.index') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Dutch Auctions</a>
            <a href="{{ route('b2b.escrow.index') }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Escrow Operations</a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Wholesale SKUs</div>
            <div class="text-3xl font-bold text-indigo-600 mt-2">{{ $audit['catalog_items_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Dutch Auctions</div>
            <div class="text-3xl font-bold text-amber-600 mt-2">{{ $audit['active_auctions_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Open RFQs</div>
            <div class="text-3xl font-bold text-purple-600 mt-2">{{ $audit['active_rfqs_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Escrow Funds Held</div>
            <div class="text-3xl font-bold text-emerald-600 mt-2">Rp {{ number_format($audit['total_escrow_held_idr'] ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Audit Health Badge -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">B2B Platform Audit Status</h3>
                <p class="text-sm text-gray-500 mt-1">Real-time ledger escrow balancing and Dutch auction price integrity checks.</p>
            </div>
            <span class="px-3 py-1 text-sm font-bold rounded-full {{ ($audit['status'] ?? '') === 'HEALTHY' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $audit['status'] ?? 'UNKNOWN' }}
            </span>
        </div>
    </div>
</div>
@endsection
