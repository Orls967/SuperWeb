@extends('layouts.app')

@section('title', 'Laporan Kontrak')
@section('subtitle', 'Eksposur kontrak, aging obligasi, risiko, dan rekonsiliasi subledger (simulasi)')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">📊 Laporan Kontrak</h1>
            <p class="text-sm text-slate-400 mt-1">Pajak, bea, arbitrase, dan skor risiko bersifat simulasi.</p>
        </div>
        <a href="{{ route('contract.obligations') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Dashboard Obligasi →</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-xs uppercase text-slate-400">Kontrak Aktif</p>
            <p class="text-3xl font-bold text-white mt-2">{{ number_format($activeContracts) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-xs uppercase text-slate-400">Over-utilization</p>
            <p class="text-3xl font-bold text-red-400 mt-2">{{ collect($over80)->filter(fn ($contract) => $contract->remainingValue() === 0)->count() }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-xs uppercase text-slate-400">Early Warning ≥80%</p>
            <p class="text-3xl font-bold text-amber-400 mt-2">{{ count($over80) }}</p>
        </div>
        <div class="bg-slate-800/50 border {{ $audit['balanced'] ? 'border-emerald-500/30' : 'border-red-500/50' }} rounded-xl p-5">
            <p class="text-xs uppercase text-slate-400">ctr:audit</p>
            <p class="text-2xl font-bold mt-2 {{ $audit['balanced'] ? 'text-emerald-400' : 'text-red-400' }}">{{ $audit['balanced'] ? 'BALANCED' : 'DISCREPANCY' }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $audit['checked'] }} kontrak · {{ count($audit['discrepancies']) }} selisih</p>
        </div>
    </div>

    @if(!$audit['balanced'])
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-sm text-red-300">
            <strong>Selisih audit:</strong>
            <ul class="mt-2 list-disc list-inside">
                @foreach($audit['discrepancies'] as $d)
                    <li>{{ $d['contract_id'] }} — {{ $d['kind'] }} — selisih {{ number_format($d['difference']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">Nilai per Jenis Kontrak</h2></div>
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Jenis</th><th class="px-4 py-3 text-right">Jumlah</th><th class="px-4 py-3 text-right">Nilai (IDR)</th><th class="px-4 py-3 text-right">Terpakai</th></tr></thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse($exposure['by_type'] as $row)
                        <tr><td class="px-4 py-3 text-slate-200">{{ str_replace('_', ' ', $row->contract_type) }}</td><td class="px-4 py-3 text-right text-slate-400">{{ number_format($row->contract_count) }}</td><td class="px-4 py-3 text-right text-white">{{ number_format($row->total_value_idr) }}</td><td class="px-4 py-3 text-right text-indigo-300">{{ number_format($row->used_value_idr) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada eksposur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">Eksposur per Pihak (Top 20)</h2></div>
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Pihak</th><th class="px-4 py-3 text-right">Kontrak</th><th class="px-4 py-3 text-right">Nilai (IDR)</th><th class="px-4 py-3 text-right">Terpakai</th></tr></thead>
                <tbody class="divide-y divide-slate-700/60">
                    @forelse(collect($exposure['by_party'])->take(20) as $row)
                        <tr><td class="px-4 py-3 text-slate-200">{{ $row->party_name }}</td><td class="px-4 py-3 text-right text-slate-400">{{ number_format($row->contract_count) }}</td><td class="px-4 py-3 text-right text-white">{{ number_format($row->total_value_idr) }}</td><td class="px-4 py-3 text-right text-indigo-300">{{ number_format($row->used_value_idr) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada eksposur.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">Aging Obligasi (IDR)</h2></div>
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-700">
            @foreach(['current' => 'Belum jatuh tempo', '1_30' => 'Lewat 1–30 hari', '31_60' => 'Lewat 31–60 hari', 'over_60' => 'Lewat >60 hari'] as $key => $label)
                <div class="p-5"><p class="text-xs text-slate-400">{{ $label }}</p><p class="text-xl font-semibold text-white mt-2">{{ number_format($aging[$key]['amount_idr']) }}</p><p class="text-xs text-slate-500 mt-1">{{ $aging[$key]['count'] }} milestone</p></div>
            @endforeach
        </div>
    </section>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">Indeks Eskalasi Tersimpan (simulasi)</h2></div>
        @forelse($indexes as $code => $observations)
            <div class="px-5 py-3 border-b border-slate-700/50 flex items-center justify-between"><span class="text-xs font-mono text-indigo-300">{{ $code }}</span><span class="text-xs text-slate-400">{{ $observations->first()->observed_at->format('Y-m-d') }} · {{ $observations->first()->value }} · {{ $observations->first()->source }}</span></div>
        @empty
            <div class="px-5 py-8 text-center text-slate-500 text-sm">Belum ada indeks eskalasi. Catat melalui console/service.</div>
        @endforelse
    </section>
</div>
@endsection
