@extends('layouts.app')

@section('title', 'EU Battery Passport Cryptographic Hash Chain')
@section('subtitle', 'Buku Besar Digital Riwayat Degradasi Siklus Baterai EV & Verifikasi SHA-256 Anti-Tamper')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-100">Buku Besar Battery Passport</h1>
            <p class="text-sm text-slate-400">Sesuai Regulasi EU Battery Directive & Standar Keterlacakan Daur Hidup ESG</p>
        </div>
        <a href="{{ route('ev.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-200 bg-slate-800/80 hover:bg-slate-700 rounded-xl border border-slate-700">
            &larr; Dashboard EV
        </a>
    </div>

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Siklus #</th>
                        <th class="p-3">Vehicle ID</th>
                        <th class="p-3">Kode Sesi</th>
                        <th class="p-3">SoH Awal / Akhir</th>
                        <th class="p-3">Delta Degradasi</th>
                        <th class="p-3">Suhu Sel Puncak</th>
                        <th class="p-3">Energi Diisi</th>
                        <th class="p-3">Block Hash (SHA-256)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 font-mono text-xs">
                    @forelse($blocks as $b)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-bold text-violet-400">#{{ $b->cycle_number }}</td>
                            <td class="p-3 text-slate-300 font-sans">VEH-{{ $b->vehicle_id }}</td>
                            <td class="p-3 text-slate-400">{{ $b->session_code }}</td>
                            <td class="p-3 text-slate-200 font-sans">{{ $b->initial_soh_pct }}% &rarr; {{ $b->final_soh_pct }}%</td>
                            <td class="p-3 text-rose-400 font-sans">-{{ $b->delta_soh }}%</td>
                            <td class="p-3 text-slate-200 font-sans">{{ $b->max_cell_temp_c }} °C</td>
                            <td class="p-3 text-slate-300 font-sans">{{ number_format($b->energy_wh) }} Wh</td>
                            <td class="p-3 text-emerald-400" title="Prev: {{ $b->prev_hash }}">
                                {{ substr($b->block_hash, 0, 16) }}...
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-500 font-sans">Belum ada blok paspor baterai tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $blocks->links() }}
        </div>
    </div>
</div>
@endsection
