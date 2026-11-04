@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Keuangan Syariah — Meja Operasional Akad Murabahah (D19-2)</h1>
        <p class="text-sm text-slate-600 mt-1">Simulasi S2 PSAK 102: Jual-beli dengan margin tetap, bebas riba/bunga, persetujuan four-eyes DPS, dan jadwal angsuran LargestRemainder.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase">Total Akad</div>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($summary['total_contracts']) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase">Piutang Murabahah Aktif</div>
            <div class="text-xl font-bold text-emerald-700 mt-1">Rp {{ number_format($summary['total_receivable_idr']) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase">Margin Diakui</div>
            <div class="text-xl font-bold text-blue-700 mt-1">Rp {{ number_format($summary['recognized_margin_idr']) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase">Dana Kebajikan (Ta'zir)</div>
            <div class="text-xl font-bold text-amber-700 mt-1">Rp {{ number_format($summary['qardhul_hasan_idr']) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode Akad</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Objek Barang</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Biaya Perolehan</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Margin Tetap</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Tenor</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($contracts as $c)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $c->contract_code }}</td>
                    <td class="px-4 py-3">{{ $c->asset_description }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($c->cost_price_idr) }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($c->margin_amount_idr) }}</td>
                    <td class="px-4 py-3 text-center">{{ $c->tenor_months }} bln</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 text-xs font-medium">{{ $c->status }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada pengajuan akad murabahah.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
