@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">EPC Construction &amp; CIP Control Center</h1>
            <p class="text-gray-600 dark:text-gray-400">Engineering, Procurement &amp; Construction progress tracking, monthly claim certification, and asset capitalization.</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('epc.claims.index') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">WBS Claims</a>
            <a href="{{ route('epc.bast.index') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg">BAST-1 Handover</a>
            <a href="{{ route('epc.capitalization.index') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg">Asset Capitalization</a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active EPC Projects</div>
            <div class="text-3xl font-bold text-blue-600 mt-2">{{ $audit['projects_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly Certificates</div>
            <div class="text-3xl font-bold text-indigo-600 mt-2">{{ $audit['certificates_count'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active CIP Cost Balance</div>
            <div class="text-3xl font-bold text-amber-600 mt-2">Rp {{ number_format($audit['total_active_cip_idr'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Capitalized Assets</div>
            <div class="text-3xl font-bold text-purple-600 mt-2">Rp {{ number_format($audit['total_capitalized_idr'] ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Audit Status Card -->
    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">EPC Audit &amp; Balance Health</h3>
                <p class="text-sm text-gray-500 mt-1">Reconciliation of certified claims against accumulated CIP and capitalized fixed asset entries.</p>
            </div>
            <span class="px-3 py-1 text-sm font-bold rounded-full {{ ($audit['status'] ?? '') === 'HEALTHY' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $audit['status'] ?? 'UNKNOWN' }}
            </span>
        </div>
    </div>
</div>
@endsection
