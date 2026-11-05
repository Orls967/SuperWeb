@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('syariah.wealth.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Wealth</a>
                <span class="text-slate-300">|</span>
                <span class="font-mono text-xs text-slate-500">Portofolio #{{ $portfolio->id }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Portofolio Investasi Syariah</h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                Profil Risiko: {{ $portfolio->risk_profile->value }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase">Total Nilai Ekuitas</div>
            <div class="text-2xl font-bold text-slate-900 mt-1">Rp {{ number_format($portfolio->total_equity_idr) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase">Saldo Kas Likuid (Wadiah)</div>
            <div class="text-2xl font-bold text-emerald-700 mt-1">Rp {{ number_format($portfolio->cash_balance_idr) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase">Total Purifikasi Amal</div>
            <div class="text-2xl font-bold text-amber-700 mt-1">Rp {{ number_format($portfolio->total_purified_charity_idr) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Riwayat Transaksi Efek & Order Pembelian</h3>
            <span class="text-xs text-slate-500">{{ $portfolio->orders->count() }} Transaksi</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Waktu</th>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Efek</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Tipe</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Jumlah Unit</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Total Nominal</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($portfolio->orders as $order)
                <tr>
                    <td class="px-4 py-2 text-xs text-slate-600">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2 font-mono text-xs font-bold">{{ $order->instrument?->ticker_symbol }}</td>
                    <td class="px-4 py-2 text-center text-xs font-medium">{{ $order->order_side }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs">{{ number_format($order->units) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs font-semibold">Rp {{ number_format($order->amount_idr) }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                            {{ $order->status }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada transaksi di portofolio ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

