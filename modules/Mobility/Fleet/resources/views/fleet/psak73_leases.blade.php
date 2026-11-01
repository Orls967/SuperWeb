@extends('layouts.app')

@section('title', 'Fleet PSAK 73 / IFRS 16 Lease Capitalization')
@section('subtitle', 'Present Value Liabilitas Sewa, Hak Guna Aset (ROU Asset), & Diskonto IBR')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Originasi Sewa PSAK 73 Baru</h3>
            <form action="{{ route('fleet.lifecycle.originate-psak73') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Nomor Kontrak</label>
                    <input type="text" name="contract_number" placeholder="FLT-PSAK-2026-001" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Party / Debitur ID</label>
                    <input type="number" name="party_id" value="1" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tenor (Bulan)</label>
                    <input type="number" name="duration_months" value="36" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Sewa Bulanan (IDR Integer)</label>
                    <input type="number" name="monthly_rental_idr" value="15000000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tingkat IBR (bps, 840 = 8.4%)</label>
                    <input type="number" name="annual_ibr_bps" value="840" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm">
                    Kapitalisasi Kontrak PSAK 73
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-100">Portofolio Kontrak Sewa Pembiayaan</h3>
                <span class="text-xs text-blue-400 font-mono">Total PV: Rp {{ number_format($totalPvLiability) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">No. Kontrak</th>
                            <th class="p-3">Tenor</th>
                            <th class="p-3">Sewa/Bulan</th>
                            <th class="p-3">PV Liabilitas</th>
                            <th class="p-3">Sisa Liabilitas</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($contracts as $c)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono font-bold text-blue-400">{{ $c->contract_number }}</td>
                                <td class="p-3">{{ $c->duration_months }} bln</td>
                                <td class="p-3 font-mono">Rp {{ number_format($c->monthly_rental_idr) }}</td>
                                <td class="p-3 font-mono text-emerald-400">Rp {{ number_format($c->present_value_liability_idr) }}</td>
                                <td class="p-3 font-mono text-amber-400">Rp {{ number_format($c->remaining_liability_idr) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 uppercase">
                                        {{ $c->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">Belum ada kontrak PSAK 73.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $contracts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
