@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 min-h-screen text-gray-100">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Header -->
        <div class="p-6 bg-gray-800/80 backdrop-blur rounded-2xl shadow-xl border border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span class="p-2 bg-blue-500/20 text-blue-400 rounded-lg">🤝</span>
                        Kliring Lab Mitra & Scorecard Vendor Outsourcing (Fase 105)
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">Penyelesaian Kewajiban Antar-Cabang (Inter-Branch Clearing) & Penilaian Performa Vendor Lab Eksternal</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.laboratory.catalog.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm font-medium transition">
                        ← Katalog LOINC
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Kontrak Mitra Lab Eksternal -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span>🏢</span> Kontrak & Scorecard Lab Mitra
                    </h2>
                    <span class="text-xs text-gray-400">{{ count($contracts) }} mitra aktif</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                            <tr>
                                <th class="p-3">Nama Mitra Lab</th>
                                <th class="p-3">SLA Turnaround</th>
                                <th class="p-3">Scorecard</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($contracts as $c)
                                <tr class="hover:bg-gray-700/40">
                                    <td class="p-3 font-medium text-white">{{ $c->partner_name }}</td>
                                    <td class="p-3 text-xs">{{ $c->sla_hours ?? 24 }} jam</td>
                                    <td class="p-3 font-semibold text-amber-400">{{ $c->quality_score ?? 98.5 }}%</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-950 text-emerald-300 border border-emerald-800">
                                            {{ $c->status ?? 'ACTIVE' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-gray-500">Belum ada kontrak lab mitra tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Inter-Branch Clearing Obligations -->
            <div class="p-6 bg-gray-800 rounded-2xl border border-gray-700 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span>💳</span> Penyelesaian Finansial Antar-Cabang (Clearing)
                    </h2>
                    <span class="text-xs text-gray-400">{{ count($clearings) }} batch clearing</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-300">
                        <thead class="bg-gray-900/60 text-xs text-gray-400 uppercase">
                            <tr>
                                <th class="p-3">Kode Kliring</th>
                                <th class="p-3">Cabang Asal → Tujuan</th>
                                <th class="p-3">Nominal IDR</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($clearings as $clr)
                                <tr class="hover:bg-gray-700/40">
                                    <td class="p-3 font-mono font-medium text-indigo-400">{{ $clr->clearing_code }}</td>
                                    <td class="p-3 text-xs">Cabang #{{ $clr->from_branch_id }} → Cabang #{{ $clr->to_branch_id }}</td>
                                    <td class="p-3 font-mono text-emerald-400 font-semibold">Rp {{ number_format($clr->amount_idr ?? 0, 0, ',', '.') }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 text-[11px] font-medium rounded {{ $clr->settlement_status === 'SETTLED' ? 'bg-emerald-900 text-emerald-200' : 'bg-amber-900 text-amber-200' }}">
                                            {{ $clr->settlement_status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-gray-500">Belum ada transaksi kliring antar cabang.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
