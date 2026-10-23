@extends('layouts.app')

@section('title', 'RWA Secondary Market Orderbook')
@section('subtitle', 'Buku Pesanan Limit Buy / Sell & Pencocokan Likuiditas Sekunder Terdesentralisasi')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Buy Orders (Bids) --}}
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-emerald-500/30 backdrop-blur-xl">
            <h3 class="text-base font-bold text-emerald-400 mb-4">Bids (Antrean Beli)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Order</th>
                            <th class="p-3">Harga Beli</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 font-mono text-xs">
                        @forelse($buyOrders as $b)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 text-slate-400">{{ $b->order_code }}</td>
                                <td class="p-3 font-bold text-emerald-400">Rp {{ number_format($b->price_per_token_idr) }}</td>
                                <td class="p-3">{{ number_format($b->quantity - $b->filled_quantity) }}</td>
                                <td class="p-3 text-slate-400 font-sans">{{ $b->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-500 font-sans">Tidak ada order beli.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sell Orders (Asks) --}}
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-rose-500/30 backdrop-blur-xl">
            <h3 class="text-base font-bold text-rose-400 mb-4">Asks (Antrean Jual)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Order</th>
                            <th class="p-3">Harga Jual</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 font-mono text-xs">
                        @forelse($sellOrders as $s)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 text-slate-400">{{ $s->order_code }}</td>
                                <td class="p-3 font-bold text-rose-400">Rp {{ number_format($s->price_per_token_idr) }}</td>
                                <td class="p-3">{{ number_format($s->quantity - $s->filled_quantity) }}</td>
                                <td class="p-3 text-slate-400 font-sans">{{ $s->status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-500 font-sans">Tidak ada order jual.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
