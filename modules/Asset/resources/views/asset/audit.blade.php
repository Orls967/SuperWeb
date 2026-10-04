@extends('layouts.app')

@section('title', 'Audit Aset')
@section('subtitle', 'Penyusutan, pemeliharaan, sewa, revaluasi, dan rekonsiliasi ledger')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">📊 Audit & TCO Aset</h1>
            <p class="text-sm text-slate-400 mt-1">PSAK 16/73, pajak, impairment, dan rekomendasi penggantian bersifat simulasi.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('asset.audit') }}">@csrf<button class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">🔄 Reconcile</button></form>
            <a href="{{ route('asset.depreciation.index') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Riwayat Penyusutan</a>
            <a href="{{ route('asset.index') }}" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Register</a>
        </div>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Aset</p><p class="text-2xl font-bold text-white">{{ number_format($counts['assets']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">WO Terbuka</p><p class="text-2xl font-bold text-amber-300">{{ number_format($counts['work_orders_open']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Sewa Aktif</p><p class="text-2xl font-bold text-indigo-300">{{ number_format($counts['leases_active']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Revaluasi Pending</p><p class="text-2xl font-bold text-purple-300">{{ number_format($counts['revaluations_pending']) }}</p></div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><p class="text-xs text-slate-400">Disposal Pending</p><p class="text-2xl font-bold text-red-300">{{ number_format($counts['disposals_pending']) }}</p></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="text-lg font-semibold text-white mb-3">Penyusutan per Periode (31.1/31.2)</h2>
            <form method="POST" action="{{ route('asset.depreciation.run') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div><label class="text-xs text-slate-400 block mb-1">Periode</label><input type="month" name="period" required value="{{ now()->format('Y-m') }}" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm"></div>
                <div><label class="text-xs text-slate-400 block mb-1">Buku</label><select name="book" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm"><option value="commercial">Komersial</option><option value="fiscal">Fiskal (simulasi)</option></select></div>
                <button class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm">Jalankan Penyusutan</button>
            </form>
            <p class="text-xs text-slate-500 mt-2">Garis lurus / saldo menurun / unit produksi; idempoten per aset-periode-buku.</p>
            <div class="mt-4 space-y-2">
                @foreach($assets->take(12) as $asset)
                    <div class="flex items-center justify-between text-xs border-t border-slate-700/60 pt-2">
                        <span class="text-slate-300">{{ $asset->asset_number }} · {{ $asset->name }}</span>
                        <span class="text-indigo-300">{{ $asset->category?->depreciation_method }} / {{ $asset->category?->useful_life_years }} th</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <h2 class="text-lg font-semibold text-white mb-3">Rekonsiliasi Subledger ↔ Ledger (31.8)</h2>
            <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl {{ $result['balanced'] ? 'text-emerald-400' : 'text-red-400' }}">{{ $result['balanced'] ? '✓' : '✗' }}</span>
                <div><p class="text-white font-semibold">{{ $result['balanced'] ? 'BALANCED · 0 selisih' : 'DISCREPANCY · '.count($result['discrepancies']).' selisih' }}</p><p class="text-xs text-slate-500">{{ $result['checked'] }} aset diperiksa</p></div>
            </div>
            @foreach($result['health_pillars'] as $pillar => $state)
                <div class="text-xs py-1 {{ $state === 'ok' ? 'text-emerald-400' : 'text-red-400' }}">{{ $state === 'ok' ? '✓' : '✗' }} {{ $pillar }}</div>
            @endforeach
            @if($result['discrepancies'])
                <div class="mt-3 space-y-1">
                    @foreach($result['discrepancies'] as $d)
                        <p class="text-xs text-red-300">{{ $d['asset'] }} · {{ $d['kind'] }} · Δ {{ number_format($d['difference']) }}</p>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700"><h2 class="font-semibold text-white">Total Cost of Ownership & Rekomendasi Ganti (31.7)</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Aset</th><th class="px-4 py-3 text-right">Susut</th><th class="px-4 py-3 text-right">Pemeliharaan</th><th class="px-4 py-3 text-right">Asuransi</th><th class="px-4 py-3 text-right">TCO</th><th class="px-4 py-3 text-right">TCO %</th><th class="px-4 py-3 text-left">Rekomendasi</th></tr></thead>
                <tbody class="divide-y divide-slate-700/60">
                    @foreach($assets as $asset)
                        @php($row = $tcoRows->firstWhere('asset_id', $asset->id))
                        @continue(!$row || $row['total_tco_idr'] === 0 && !$row['recommend_replace'])
                        <tr><td class="px-4 py-3"><a href="{{ route('asset.show', $asset) }}" class="text-indigo-300 hover:underline">{{ $asset->asset_number }} · {{ $asset->name }}</a></td><td class="px-4 py-3 text-right text-slate-300">{{ number_format($row['depreciation_idr']) }}</td><td class="px-4 py-3 text-right text-slate-300">{{ number_format($row['maintenance_idr']) }}</td><td class="px-4 py-3 text-right text-slate-300">{{ number_format($row['insurance_idr']) }}</td><td class="px-4 py-3 text-right text-white font-semibold">{{ number_format($row['total_tco_idr']) }}</td><td class="px-4 py-3 text-right">{{ $row['tco_percent'] }}%</td><td class="px-4 py-3">{{ $row['recommend_replace'] ? '⚠️ Ganti' : '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
