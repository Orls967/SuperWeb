@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.bmt.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">{{ $cooperative->cooperative_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $cooperative->name }}</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">No. Izin Koperasi</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ $cooperative->legal_entity_number }}</div>
            <div class="text-xs text-slate-500 mt-1">Domisili: {{ $cooperative->city }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dewan Pengawas Syariah</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ $cooperative->dps_advisor }}</div>
            <div class="text-xs text-slate-500 mt-1">Sertifikasi DSN-MUI</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Simpanan Wadiah/Mudharabah</div>
            <div class="mt-1 text-lg font-bold text-emerald-700 font-mono">Rp {{ number_format($cooperative->savings->sum('balance_idr') / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $cooperative->savings->count() }} rekening nasabah</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Daftar Rekening Simpanan Anggota</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">No. Rekening</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Nasabah Party</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Akad Simpanan</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Saldo</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($cooperative->savings as $sav)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $sav->account_number }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $sav->customer_party_ref }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $sav->contract_type }}</td>
                            <td class="px-4 py-2 text-right font-mono text-slate-900 font-medium">Rp {{ number_format($sav->balance_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('syariah.bmt.savings.show', $sav->id) }}" class="text-emerald-600 hover:text-emerald-800 font-medium">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-slate-500">Belum ada rekening simpanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
