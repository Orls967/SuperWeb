@extends('layouts.app')

@section('title', 'Buku Besar Piutang Kota (City Ledger) & Penagihan Korporat — Superweb Hotel')

@section('content')
<div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-blue-50 text-blue-700 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Portal Buku Besar Piutang Korporat (City Ledger AR)</h1>
                    <p class="text-sm text-slate-500">Rekonsiliasi Direct Billing Perusahaan, Pemantauan Pagu Kredit & Jadwal Umur Piutang (Aging)</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                    + Daftarkan Akun Korporat Baru
                </button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Perusahaan Rekanan Aktif</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ count($accounts) }} Perusahaan</p>
                <span class="text-xs text-slate-400 mt-1 block">Kontrak Direct Corporate Billing</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Pagu Kredit Disetujui</span>
                <p class="text-2xl font-bold text-slate-900 mt-1">IDR {{ number_format((float) $accounts->sum('credit_limit_idr'), 0, ',', '.') }}</p>
                <span class="text-xs text-slate-400 mt-1 block">Plafon penjaminan biaya inap</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Saldo Piutang Berjalan</span>
                <p class="text-2xl font-bold text-blue-600 mt-1">IDR {{ number_format((float) $accounts->sum('outstanding_balance_idr'), 0, ',', '.') }}</p>
                <span class="text-xs text-blue-500 mt-1 block">Akun Ledger htl:city_ledger:IDR</span>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Piutang Jatuh Tempo (> 60 Hari)</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">IDR 0</p>
                <span class="text-xs text-emerald-500 mt-1 block">Kolektibilitas lancar (Current)</span>
            </div>
        </div>

        <!-- Accounts Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Daftar Akun Mitra Bisnis & Pagu Piutang</h2>
                    <p class="text-xs text-slate-500">Pemberlakuan isolasi batas kredit untuk mencegah pemesanan over-limit</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-3">Kode Mitra</th>
                            <th class="px-6 py-3">Nama Korporasi</th>
                            <th class="px-6 py-3 text-right">Pagu Kredit (IDR)</th>
                            <th class="px-6 py-3 text-right">Saldo Terutang (IDR)</th>
                            <th class="px-6 py-3 text-right">Sisa Pagu Tersedia</th>
                            <th class="px-6 py-3 text-center">Status Akun</th>
                            <th class="px-6 py-3 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($accounts as $acc)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-mono font-bold text-xs text-indigo-600">{{ $acc->company_code }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900">{{ $acc->company_name }}</td>
                                <td class="px-6 py-4 text-right font-mono text-slate-900">
                                    IDR {{ number_format((float) $acc->credit_limit_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-bold text-blue-600">
                                    IDR {{ number_format((float) $acc->outstanding_balance_idr, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-emerald-600">
                                    IDR {{ number_format(max(0, (float) ($acc->credit_limit_idr - $acc->outstanding_balance_idr)), 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $acc->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $acc->is_active ? 'ACTIVE' : 'SUSPENDED' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition">
                                        Cetak Faktur Bulanan
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-slate-400 italic">Belum ada akun City Ledger terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Matriks Aging AR Table Component -->
        @include('hotel::components.city_ledger_aging_table', ['accounts' => $accounts])
    </div>
</div>
@endsection
