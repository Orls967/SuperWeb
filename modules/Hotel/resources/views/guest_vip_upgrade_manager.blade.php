@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pemberian Upgrade Kamar VIP & Tamu Loyal (Complimentary Upgrade)</h1>
            <p class="text-sm text-slate-500">Persetujuan upgrade ke kategori Suite untuk anggota Platinum dan penanganan keluhan tamu.</p>
        </div>
    </div>

    <!-- Tabel Daftar Tamu Berhak Upgrade -->
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">No. Reservasi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Nama Tamu & Tier</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Tipe Kamar Dipesan</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Target Upgrade</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Ketersediaan Suite</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <tr>
                    <td class="px-6 py-4 font-mono font-medium text-slate-900">RSV-2026-91090</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">Hartono Widjaja</div>
                        <span class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Platinum Member</span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">Deluxe Ocean View</td>
                    <td class="px-6 py-4 font-semibold text-indigo-700">Executive Ocean Suite</td>
                    <td class="px-6 py-4 text-center">
                        <span class="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">Tersedia 2 Kamar</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button type="button" class="inline-flex items-center rounded bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                            Setujui Upgrade Gratis
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
