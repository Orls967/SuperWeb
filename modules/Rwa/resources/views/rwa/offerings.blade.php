@extends('layouts.app')

@section('title', 'RWA Primary Escrow Offerings')
@section('subtitle', 'Penawaran Perdana Token Aset Riil Beragun Properti, Armada & Fasilitas')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Penawaran Pasar Perdana Aktif</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Kode Offering</th>
                        <th class="p-3">Aset Riil</th>
                        <th class="p-3">Harga/Token</th>
                        <th class="p-3">Progres Penjualan</th>
                        <th class="p-3">Lockup</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($offerings as $o)
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-violet-400">{{ $o->offering_code }}</td>
                            <td class="p-3 text-xs">{{ optional($o->asset)->name ?? '-' }} ({{ optional($o->asset)->token_symbol ?? '-' }})</td>
                            <td class="p-3 font-mono">Rp {{ number_format($o->price_per_token_idr) }}</td>
                            <td class="p-3 font-mono text-xs">
                                {{ number_format($o->tokens_sold) }} / {{ number_format($o->tokens_offered) }}
                                ({{ round(($o->tokens_sold / max(1, $o->tokens_offered)) * 100, 1) }}%)
                            </td>
                            <td class="p-3">{{ $o->lockup_days }} hari</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded {{ $o->status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400' }} uppercase">
                                    {{ $o->status }}
                                </span>
                            </td>
                            <td class="p-3">
                                @if($o->status === 'active')
                                    <form action="{{ route('rwa.exchange.subscribe-offering') }}" method="POST" class="inline-flex gap-1 items-center">
                                        @csrf
                                        <input type="hidden" name="offering_id" value="{{ $o->id }}">
                                        <input type="number" name="quantity" value="{{ $o->min_lot_size }}" min="{{ $o->min_lot_size }}" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-xs text-slate-100">
                                        <button type="submit" class="px-2.5 py-1 bg-violet-600 hover:bg-violet-500 text-white rounded-lg text-xs font-semibold">
                                            Beli Lot
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500">Tutup</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">Belum ada offering aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $offerings->links() }}
        </div>
    </div>
</div>
@endsection
