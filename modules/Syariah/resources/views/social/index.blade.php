@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Indeks Dampak Sosial Syariah (Social Impact Scoring)</h1>
            <p class="text-sm text-slate-600 mt-1">Pengukuran dampak pemberdayaan ekonomi mikro syariah: penyerapan tenaga kerja, transformasi mustahik menjadi muzakki, dan monitoring rasio NPF mikro.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">Metrik Dampak Sosial Tahunan</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Tahun Periode</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Penerima Manfaat</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Tenaga Kerja Terserap</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Transformasi Mustahik &rarr; Muzakki</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Kenaikan Omzet</th>
                        <th class="px-4 py-2 text-center font-semibold text-slate-700">Rasio NPF Mikro</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($metrics as $met)
                        <tr>
                            <td class="px-4 py-2 font-mono font-bold text-emerald-700">{{ $met->period_year }}</td>
                            <td class="px-4 py-2 text-center font-mono font-medium">{{ number_format($met->total_beneficiaries) }} org</td>
                            <td class="px-4 py-2 text-center font-mono font-bold text-slate-900">+{{ number_format($met->jobs_created) }} lapangan kerja</td>
                            <td class="px-4 py-2 text-center font-mono font-bold text-emerald-600">{{ number_format($met->mustahik_to_muzakki_count) }} muzakki baru</td>
                            <td class="px-4 py-2 text-center font-mono font-medium text-emerald-700">+{{ number_format($met->avg_income_increase_bps / 100, 1) }}%</td>
                            <td class="px-4 py-2 text-center font-mono font-medium text-slate-700">{{ number_format($met->micro_npf_ratio_bps / 100, 2) }}%</td>
                            <td class="px-4 py-2 text-slate-500 text-xs">{{ $met->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-slate-500">Belum ada catatan metrik dampak sosial tahunan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
