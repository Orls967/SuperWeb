@extends('layouts.app')

@section('title', 'Forward FX Hedging Contracts')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Forward FX Hedging Contracts</h1>
            <p class="text-sm text-gray-500">Lindung nilai fluktuasi kurs mata uang asing untuk kewajiban impor dan penerimaan ekspor.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
            FX Derivatives
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Total Notional Forward</h3>
            <p class="text-3xl font-bold text-gray-900">$12,000,000</p>
            <p class="text-xs text-gray-500 mt-1">Underlying kontrak impor</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Mark-to-Market (MTM)</h3>
            <p class="text-3xl font-bold text-emerald-600">+Rp 840.000.000</p>
            <p class="text-xs text-gray-500 mt-1">Valuasi wajar berjalan</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Kontrak Jatuh Tempo &lt; 30 Hari</h3>
            <p class="text-3xl font-bold text-indigo-600">3 Kontrak</p>
            <p class="text-xs text-gray-500 mt-1">Siap settlement dengan bank</p>
        </div>
    </div>
</div>
@endsection
