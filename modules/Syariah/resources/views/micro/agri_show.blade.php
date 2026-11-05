@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('syariah.micro.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali ke Mikro</a>
                <span class="text-slate-300">/</span>
                <span class="font-mono text-sm text-emerald-700">{{ $facility->facility_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Pembiayaan Salam Pertanian Berbasis Satelit</h1>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Petani & Komoditas</div>
            <div class="mt-1 text-base font-bold text-slate-900">{{ $facility->farmer_party_ref }}</div>
            <div class="text-xs text-slate-500 mt-1">Komoditas: {{ $facility->commodity }} ({{ $facility->plot_code }})</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Indeks Vegetasi NDVI G7-K1</div>
            <div class="mt-1 text-2xl font-bold font-mono {{ $facility->current_ndvi_bps >= $facility->ndvi_min_threshold_bps ? 'text-emerald-600' : 'text-amber-600' }}">
                {{ number_format($facility->current_ndvi_bps / 10000, 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Ambang Batas: {{ number_format($facility->ndvi_min_threshold_bps / 10000, 2) }}</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nilai Kontrak Salam</div>
            <div class="mt-1 text-lg font-bold text-slate-900 font-mono">Rp {{ number_format($facility->financing_amount_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Target Panen: {{ number_format($facility->expected_yield_kg) }} kg</div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pencairan & Saldo</div>
            <div class="mt-1 text-lg font-bold text-emerald-700 font-mono">Rp {{ number_format($facility->disbursed_amount_idr / 100, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-500 mt-1">Sisa Pokok: Rp {{ number_format($facility->outstanding_balance_idr / 100, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Milestone Pencairan Bertahap Berbasis NDVI</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Kode Milestone</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Fase Tanam</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Target NDVI</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Porsi</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Nominal</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($facility->milestones as $mls)
                        <tr>
                            <td class="px-4 py-2 font-mono text-emerald-700">{{ $mls->milestone_code }}</td>
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $mls->title }}</td>
                            <td class="px-4 py-2 text-center font-mono font-medium text-slate-700">{{ number_format($mls->target_ndvi_bps / 10000, 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ $mls->disbursement_percentage }}%</td>
                            <td class="px-4 py-2 text-right font-mono font-medium text-slate-900">Rp {{ number_format($mls->amount_idr / 100, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($mls->status === 'DISBURSED')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">Cair</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">Menunggu NDVI</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-slate-500">Belum ada milestone tani terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
