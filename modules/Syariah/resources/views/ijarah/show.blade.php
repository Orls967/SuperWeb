@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('syariah.ijarah.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali</a>
                <span class="text-slate-300">|</span>
                <span class="font-mono text-xs text-slate-500">{{ $facility->contract_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">Akad Sewa: {{ $facility->asset_name }}</h1>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                {{ $facility->status->value === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                Status: {{ $facility->status->value }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Parameter Akad</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Nasabah Penyewa:</dt><dd class="font-bold text-slate-900">{{ $facility->customer?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Biaya Perolehan Aset:</dt><dd class="font-mono text-slate-900">Rp {{ number_format($facility->asset_cost_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Total Ujrah Sewa:</dt><dd class="font-mono font-semibold text-emerald-700">Rp {{ number_format($facility->total_ujrah_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Tenor Pembiayaan:</dt><dd class="text-slate-800">{{ $facility->tenor_months }} Bulan</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Opsi Beli Akhir (IMBT):</dt><dd class="font-mono text-slate-800">Rp {{ number_format($facility->residual_option_price_idr) }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Posisi Buku & Penyusutan</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Nilai Buku Berjalan:</dt><dd class="font-mono font-semibold text-slate-900">Rp {{ number_format($facility->carrying_book_value_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Akumulasi Penyusutan:</dt><dd class="font-mono text-slate-700">Rp {{ number_format($facility->accumulated_depreciation_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Total Ujrah Terbayar:</dt><dd class="font-mono text-blue-700 font-semibold">Rp {{ number_format($facility->total_ujrah_paid_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Denda Ta'zir (Kebajikan):</dt><dd class="font-mono text-amber-700">Rp {{ number_format($facility->total_tazir_idr) }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Pengawasan Syariah & Jadwal</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Reviewer DPS:</dt><dd class="text-slate-800">{{ $facility->dpsReviewer?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Tanggal Aktivasi:</dt><dd class="text-slate-800">{{ $facility->activated_at?->format('d/m/Y') ?? '—' }}</dd></div>
                <div class="pt-2">
                    <dt class="text-xs text-slate-500">Opini DPS:</dt>
                    <dd class="mt-1 text-xs italic text-slate-700 bg-slate-50 p-2 rounded border border-slate-100">{{ $facility->dps_opinion_note ?? 'Menunggu reviu Dewan Pengawas Syariah' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Jadwal Angsuran Ujrah & Amortisasi Penyusutan</h3>
            <span class="text-xs text-slate-500">{{ $facility->schedules->count() }} Angsuran</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Ke-</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Jatuh Tempo</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Angsuran Ujrah</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Penyusutan</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Sisa Nilai Buku</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Tanggal Bayar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($facility->schedules as $sch)
                <tr class="{{ $sch->status === 'PAID' ? 'bg-emerald-50/30' : '' }}">
                    <td class="px-4 py-2 text-center font-medium">{{ $sch->period }}</td>
                    <td class="px-4 py-2 text-center text-xs text-slate-600">{{ $sch->due_date?->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs font-semibold">Rp {{ number_format($sch->ujrah_idr) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs text-slate-600">Rp {{ number_format($sch->depreciation_idr) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs text-slate-700">Rp {{ number_format($sch->carrying_book_value_idr) }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sch->status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ $sch->status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-center text-xs text-slate-500">{{ $sch->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Jadwal belum digenerate.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

