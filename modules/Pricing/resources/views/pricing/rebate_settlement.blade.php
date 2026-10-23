@extends('layouts.app')

@section('title', 'Off-Invoice Rebate Claims & Settlement')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Off-Invoice Rebate Claims</h1>
            <p class="text-sm text-gray-500">Penyelesaian klaim rebate target penjualan distributor dengan bukti dan approval.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Rebate Settlement
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Daftar Klaim Rebate Siap Diproses</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3">Distributor</th>
                        <th class="px-4 py-3">Program Promo</th>
                        <th class="px-4 py-3">Nilai Pengajuan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">PT Sinar Distribusi Utama</td>
                        <td class="px-4 py-3 text-gray-500">Kuartal Q3 Volume Incentive</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">Rp 15.000.000</td>
                        <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800">Verified</span></td>
                        <td class="px-4 py-3">
                            <button class="px-3 py-1 bg-emerald-600 text-white rounded text-xs hover:bg-emerald-700">Settle Claim</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
