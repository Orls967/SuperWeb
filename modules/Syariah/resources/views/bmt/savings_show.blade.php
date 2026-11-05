@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.bmt.show', $savings->cooperative->id ?? 1) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke BMT</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">{{ $savings->account_number }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Buku Tabungan Syariah BMT</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pemilik Rekening (Party)</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ $savings->customer_party_ref }}</div>
            <div class="text-xs text-slate-500 mt-1">BMT: {{ $savings->cooperative->name ?? '-' }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Akad Syariah</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ $savings->contract_type }}</div>
            <div class="text-xs text-emerald-600 mt-1">Bebas Bunga Riba Mutlak</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Saldo Tabungan Saat Ini</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700 font-mono">Rp {{ number_format($savings->balance_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Status: {{ $savings->is_active ? 'Aktif' : 'Tutup' }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Riwayat Mutasi Tabungan</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Ref Transaksi</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Jenis</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Nominal</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Saldo Akhir</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Keterangan</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($savings->transactions as $tx)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $tx->transaction_ref }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                @if($tx->type === 'DEPOSIT')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">Setoran</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Penarikan</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono font-medium {{ $tx->type === 'DEPOSIT' ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $tx->type === 'DEPOSIT' ? '+' : '-' }} Rp {{ number_format($tx->amount_idr / 100, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-slate-900 font-medium">Rp {{ number_format($tx->balance_after_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $tx->note ?? '-' }}</td>
                            <td class="px-4 py-2 text-slate-500 text-xs">{{ $tx->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500">Belum ada mutasi transaksi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
