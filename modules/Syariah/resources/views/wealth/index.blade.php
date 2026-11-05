@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Kekayaan & Investasi Syariah (D162-3)</h1>
            <p class="text-sm text-slate-600 mt-1">Screening saham/sukuk POJK 35 (utang bunga &le; 45%, pendapatan non-halal &le; 10%), purifikasi dividen otomatis, dan alokasi portofolio sesuai profil risiko.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('syariah.wealth.export') }}" class="inline-flex items-center px-3 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50">
                Ekspor Screening CSV
            </a>
        </div>
    </div>

    @if($portfolio)
    <div class="bg-gradient-to-r from-emerald-800 to-teal-900 rounded-lg text-white p-6 mb-8 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="text-xs font-semibold tracking-wider uppercase text-emerald-200">Portofolio Saya</span>
                <h2 class="text-2xl font-bold mt-1">Rp {{ number_format($portfolio->total_equity_idr) }}</h2>
                <p class="text-xs text-emerald-200 mt-1">Profil Risiko: <span class="font-semibold text-white">{{ $portfolio->risk_profile->value }}</span> | Sisa Kas IDR: Rp {{ number_format($portfolio->cash_balance_idr) }}</p>
            </div>
            <div>
                <a href="{{ route('syariah.wealth.portfolios.show', $portfolio->id) }}" class="inline-flex items-center px-4 py-2 rounded bg-white text-emerald-900 text-sm font-semibold hover:bg-emerald-50">
                    Buka Detail Portofolio &rarr;
                </a>
            </div>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Daftar Efek Syariah (DES) & Hasil Uji Screening Kepatuhan</h3>
            <span class="text-xs text-slate-500">{{ $instruments->count() }} Efek Terdaftar</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Ticker</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Nama Entitas</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Tipe</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Rasio Utang Bunga</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Rasio Non-Halal</th>
                    <th class="px-4 py-3 text-center font-semibold text-slate-700">Status Syariah</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Sektor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($instruments as $inst)
                <tr class="hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-mono font-bold text-xs text-slate-900">{{ $inst->ticker_symbol }}</td>
                    <td class="px-4 py-3 text-slate-800">{{ $inst->name }}</td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ $inst->instrument_type }}</td>
                    <td class="px-4 py-3 text-right text-xs font-mono {{ $inst->debt_ratio_bps > 4500 ? 'text-rose-600 font-bold' : 'text-slate-700' }}">
                        {{ number_format($inst->debt_ratio_bps / 100, 2) }}%
                    </td>
                    <td class="px-4 py-3 text-right text-xs font-mono {{ $inst->non_halal_revenue_ratio_bps > 1000 ? 'text-rose-600 font-bold' : 'text-slate-700' }}">
                        {{ number_format($inst->non_halal_revenue_ratio_bps / 100, 2) }}%
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                            {{ $inst->is_shariah_compliant ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $inst->is_shariah_compliant ? 'LOLOS (DES)' : 'TIDAK SYARIAH' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ $inst->sector_code }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada instrumen investasi tercatat.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
