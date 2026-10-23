@extends('layouts.app')

@section('title', 'Requisition to RFQ Conversion')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">RFQ Conversion Hub</h1>
            <p class="text-sm text-gray-500">Konversi purchase requisition terverifikasi menjadi Request for Quotation (RFQ).</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
            Fase 33 Sourcing
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Pending Requisitions for RFQ</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3">Req Number</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Estimated Budget</th>
                        <th class="px-4 py-3">Required Date</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">PR-2026-10-004</td>
                        <td class="px-4 py-3 text-gray-500">Plant Maintenance</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp 45.000.000</td>
                        <td class="px-4 py-3 text-gray-500">2026-11-01</td>
                        <td class="px-4 py-3">
                            <button class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">Convert to RFQ</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
