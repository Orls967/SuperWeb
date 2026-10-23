@extends('layouts.app')

@section('title', 'Automated Portfolio Rebalancing')
@section('subtitle', 'Pendeteksian Penyimpangan Bobot (Drift) & Eksekusi Order Penyeimbangan Idempoten')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Portofolio Investor & Rebalancing</h3>
            <div class="space-y-4">
                @forelse($profiles as $pr)
                    <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-200">{{ optional($pr->user)->name ?? "Profile #{$pr->id}" }}</p>
                            <p class="text-xs text-slate-400">Total Aset: Rp {{ number_format($pr->holdings->sum('value_idr')) }}</p>
                        </div>
                        <form action="{{ route('wealth.treasury.rebalance') }}" method="POST">
                            @csrf
                            <input type="hidden" name="profile_id" value="{{ $pr->id }}">
                            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold">
                                Rebalance Portofolio
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-slate-500 text-sm">Tidak ada portofolio investor.</p>
                @endforelse
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Log Eksekusi Order Rebalancing Terbaru</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-2">Investor</th>
                            <th class="p-2">Aset</th>
                            <th class="p-2">Jumlah</th>
                            <th class="p-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 font-mono text-xs">
                        @forelse($recentOrders as $ro)
                            <tr>
                                <td class="p-2 font-sans">{{ optional($ro->profile?->user)->name ?? '-' }}</td>
                                <td class="p-2 uppercase text-violet-400 font-bold">{{ $ro->asset_type }}</td>
                                <td class="p-2 font-bold text-emerald-400">Rp {{ number_format($ro->amount_idr) }}</td>
                                <td class="p-2 text-slate-400 font-sans">{{ $ro->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-500 font-sans">Belum ada order dieksekusi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
