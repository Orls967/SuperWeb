@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.micro.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Mikro</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">{{ $group->group_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $group->group_name }}</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Akad Syariah</div>
            <div class="mt-1 text-base font-bold text-slate-900">{{ $group->shariah_contract }}</div>
            <div class="text-xs text-emerald-600 mt-1">Nisbah BMT: {{ $group->profit_nisbah_shohibul_maal_bps / 100 }}%</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Fasilitas Kelompok</div>
            <div class="mt-1 text-lg font-bold text-slate-900 font-mono">Rp {{ number_format($group->total_facility_amount_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $group->total_members }} anggota terdaftar</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sisa Saldo Pokok</div>
            <div class="mt-1 text-lg font-bold text-emerald-700 font-mono">Rp {{ number_format($group->outstanding_balance_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Status: {{ $group->status }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggung Renteng</div>
            <div class="mt-1 text-lg font-bold text-emerald-600">AKTIF</div>
            <div class="text-xs text-slate-500 mt-1">Solidaritas Risiko Bersama</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Daftar Anggota Kelompok & Porsi Pembiayaan</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Party ID</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Nama Anggota</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Porsi Pembiayaan</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($group->members as $member)
                        <tr>
                            <td class="px-4 py-2 font-mono text-slate-600">{{ $member->member_party_ref }}</td>
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $member->member_name }}</td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-slate-900">Rp {{ number_format($member->share_amount_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Aktif</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-slate-500">Belum ada anggota kelompok terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
