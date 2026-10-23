@extends('layouts.app')

@section('title', 'Modern Portfolio Theory (MPT) Asset Allocation')
@section('subtitle', 'Bobot Efisien Portofolio: Reksa Dana Pasar Uang/Obligasi, Emas Fisik & Kripto')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Daftar Rencana Alokasi MPT Investor</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Investor</th>
                        <th class="p-3">Profil Resiko</th>
                        <th class="p-3">Reksa Dana (%)</th>
                        <th class="p-3">Emas (%)</th>
                        <th class="p-3">Kripto (%)</th>
                        <th class="p-3">Total Bobot</th>
                        <th class="p-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($plans as $pl)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-semibold text-slate-200">{{ optional($pl->profile?->user)->name ?? '-' }}</td>
                            <td class="p-3 uppercase text-xs font-bold text-violet-400">{{ optional($pl->profile)->risk_profile ?? '-' }}</td>
                            <td class="p-3 font-mono font-bold text-blue-400">{{ $pl->allocation_mutual_funds_pct }}%</td>
                            <td class="p-3 font-mono font-bold text-amber-400">{{ $pl->allocation_gold_pct }}%</td>
                            <td class="p-3 font-mono font-bold text-rose-400">{{ $pl->allocation_crypto_pct }}%</td>
                            <td class="p-3 font-mono text-emerald-400 font-bold">
                                {{ $pl->allocation_mutual_funds_pct + $pl->allocation_gold_pct + $pl->allocation_crypto_pct }}%
                            </td>
                            <td class="p-3">
                                <form action="{{ route('wealth.treasury.allocate-mpt') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="profile_id" value="{{ $pl->profile_id }}">
                                    <button type="submit" class="px-2.5 py-1 bg-violet-600 hover:bg-violet-500 text-white rounded-lg text-xs font-semibold">
                                        Kalibrasi Ulang MPT
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">Belum ada rencana alokasi MPT.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $plans->links() }}
        </div>
    </div>
</div>
@endsection
