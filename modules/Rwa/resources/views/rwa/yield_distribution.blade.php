@extends('layouts.app')

@section('title', 'RWA Rental Yield & Operating Dividend Distribution')
@section('subtitle', 'Distribusi Pro-Rata Hasil Sewa Properti/Aset Riil ke Seluruh Pemegang Token Terverifikasi')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Distribusi Yield / Dividen Baru</h3>
            <form action="{{ route('rwa.exchange.distribute-yield') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Aset RWA</label>
                    <select name="rwa_asset_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($assets as $a)
                            <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->token_symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Pool Pendapatan Bersih (IDR Integer)</label>
                    <input type="number" name="revenue_pool_idr" value="50000000" min="1000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm">
                    Bagikan Yield Pro-Rata
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Riwayat Distribusi Dividen</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Tanggal</th>
                            <th class="p-3">Aset</th>
                            <th class="p-3">Total Pool (IDR)</th>
                            <th class="p-3">Terdistribusi (IDR)</th>
                            <th class="p-3">Cadangan Rounding</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($dividends as $d)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 text-xs text-slate-400">{{ optional($d->distribution_date)->format('d M Y') ?? '-' }}</td>
                                <td class="p-3 font-mono font-bold text-violet-400 text-xs">{{ optional($d->asset)->token_symbol ?? '-' }}</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($d->total_revenue_pool_idr) }}</td>
                                <td class="p-3 font-mono text-xs text-emerald-400 font-bold">Rp {{ number_format($d->distributed_idr) }}</td>
                                <td class="p-3 font-mono text-xs text-amber-400">Rp {{ number_format($d->rounding_reserve_idr) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 uppercase">
                                        {{ $d->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">Belum ada pembagian dividen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $dividends->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
