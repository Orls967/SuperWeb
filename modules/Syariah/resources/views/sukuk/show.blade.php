@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('syariah.sukuk.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Kembali</a>
                <span class="text-slate-300">|</span>
                <span class="font-mono text-xs text-slate-500">{{ $facility->sukuk_code }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $facility->facility_name }}</h1>
        </div>
        <div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                {{ $facility->status->value === 'ISSUED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                Status: {{ $facility->status->value }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Struktur Finansial</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Nilai Nominal:</dt><dd class="font-bold text-slate-900">Rp {{ number_format($facility->face_value_idr) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Total Unit Token:</dt><dd class="font-mono text-slate-800">{{ number_format($facility->total_units) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Imbalan / Kupon Tahunan:</dt><dd class="font-semibold text-emerald-700">{{ number_format($facility->annual_coupon_bps / 100, 2) }}% ({{ $facility->annual_coupon_bps }} bps)</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Jatuh Tempo:</dt><dd class="text-slate-800">{{ $facility->maturity_date?->format('d F Y') }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Aset RWA & Kustodian SPV</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Kode Aset RWA:</dt><dd class="font-mono font-medium text-slate-800">{{ $facility->asset_code }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Entitas SPV Pengelola:</dt><dd class="text-slate-800">{{ $facility->spv_party_name ?? 'SPV Syariah Terintegrasi' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Tanggal Terbit:</dt><dd class="text-slate-800">{{ $facility->issued_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Total Kupon Terbayar:</dt><dd class="font-semibold text-slate-900">Rp {{ number_format($facility->total_coupon_distributed_idr) }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-sm">
            <h3 class="text-xs font-semibold text-slate-500 uppercase">Kepatuhan DPS (Four-Eyes)</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Pengawas Syariah:</dt><dd class="text-slate-800">{{ $facility->dpsReviewer?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Waktu Opini:</dt><dd class="text-slate-800">{{ $facility->dps_reviewed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div class="pt-2">
                    <dt class="text-xs text-slate-500">Catatan Opini Syariah:</dt>
                    <dd class="mt-1 text-xs italic text-slate-700 bg-slate-50 p-2 rounded border border-slate-100">{{ $facility->dps_opinion_note ?? 'Belum ada opini resmi DPS tercatat.' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 text-sm">Riwayat Distribusi Imbal Hasil Kupon</h3>
            <span class="text-xs text-slate-500">{{ $facility->distributions->count() }} Periode</span>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Periode</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Pool Kupon (IDR)</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Alokasi Terbayar (IDR)</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Tanggal Distribusi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($facility->distributions as $dist)
                <tr>
                    <td class="px-4 py-2 font-mono text-xs font-semibold">{{ $dist->period_code }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs">Rp {{ number_format($dist->coupon_pool_idr) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-xs font-semibold text-emerald-700">Rp {{ number_format($dist->allocated_coupon_idr) }}</td>
                    <td class="px-4 py-2 text-center text-xs text-slate-600">{{ $dist->distributed_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada distribusi kupon untuk fasilitas ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

