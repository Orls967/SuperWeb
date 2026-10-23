@extends('layouts.app')

@section('title', 'Partner Governance & Ecosystem Audit')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Partner Governance & Audit</h1>
            <p class="text-sm text-gray-500">Audit menyeluruh reputasi mitra strategis, kepatuhan kontrak, dan risiko ekosistem.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            Due Diligence & Audit
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Partner Health Index</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 uppercase font-semibold">Financial Integrity</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">96 / 100</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 uppercase font-semibold">IP Protection</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">98 / 100</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 uppercase font-semibold">Contract SLA</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">94 / 100</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 uppercase font-semibold">Co-Dev Execution</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">92 / 100</p>
            </div>
        </div>
    </div>
</div>
@endsection
