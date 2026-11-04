@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Dewan Pengawas Syariah (DPS) — Reviu Kepatuhan Akad (Four-Eyes)</h1>
        <p class="text-sm text-slate-600 mt-1">Memastikan ketiadaan unsur riba/bunga, kejelasan barang & margin tetap sebelum bank membeli barang murabahah.</p>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kode Akad</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Objek Barang</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Harga Jual</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Opini DPS</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($contracts as $c)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $c->contract_code }}</td>
                    <td class="px-4 py-3">{{ $c->asset_description }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($c->total_selling_price_idr) }}</td>
                    <td class="px-4 py-3">{{ $c->status }}</td>
                    <td class="px-4 py-3">{{ $c->dps_opinion_note ?? 'Menunggu reviu DPS' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">Tidak ada antrean akad untuk direviu DPS.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
