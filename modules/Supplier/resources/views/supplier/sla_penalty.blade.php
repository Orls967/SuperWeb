@extends('layouts.app')

@section('title', 'Supplier SLA & Penalty Assessment')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Supplier SLA Penalties</h1>
            <p class="text-sm text-gray-500">Kalkulasi penalti keterlambatan pengiriman dan toleransi cacat mutu barang.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
            SLA Enforcement
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Ringkasan Denda & Penalti Bulan Ini</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3">PO Reference</th>
                        <th class="px-4 py-3">Keterlambatan</th>
                        <th class="px-4 py-3">Reject Rate</th>
                        <th class="px-4 py-3">Total Penalti (IDR)</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">PT Mitra Pangan Global</td>
                        <td class="px-4 py-3 text-gray-500">PO-2026-10-0012</td>
                        <td class="px-4 py-3 text-rose-600">3 Hari</td>
                        <td class="px-4 py-3 text-gray-500">0.4%</td>
                        <td class="px-4 py-3 font-semibold text-rose-600">Rp 1.500.000</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-rose-100 text-rose-800">Deducted</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
