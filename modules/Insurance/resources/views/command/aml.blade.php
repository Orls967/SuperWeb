@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pemantauan Anti-Pencucian Uang & Pencegahan Pendanaan Terorisme (APUPPT)</h1>
            <p class="text-sm text-gray-600">Pendeteksian transaksi mencurigakan, rapid surrender, dan akumulasi premi tunai nilai tinggi</p>
        </div>
        <a href="{{ route('insurance.command.dashboard') }}" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">Kembali ke Command Center</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Total Kasus Terdeteksi</span>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $report['total_flagged_cases'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Kategori Risiko Tinggi (High)</span>
            <p class="text-2xl font-bold text-amber-600 mt-2">{{ $report['high_risk_count'] ?? 0 }}</p>
        </div>
        <div class="rounded-lg bg-white p-6 shadow">
            <span class="text-sm text-gray-500">Kategori Kritis (Critical Suspicious)</span>
            <p class="text-2xl font-bold text-rose-600 mt-2">{{ $report['critical_risk_count'] ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe Peringatan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referensi Polis</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nominal Terkait</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tingkat Risiko</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Indikator AML</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse(($report['cases'] ?? []) as $c)
                <tr>
                    <td class="px-6 py-4 text-sm font-mono font-bold text-indigo-600">{{ $c['type'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $c['policy_ref'] }}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-900">Rp {{ number_format($c['amount_idr'], 0, ',', '.') }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $c['risk_level'] === 'CRITICAL' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $c['risk_level'] }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 max-w-md">{{ $c['indicator'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Tidak ada transaksi yang mencurigakan saat ini (Portofolio Bersih AML).</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

