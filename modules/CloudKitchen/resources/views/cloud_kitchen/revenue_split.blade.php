@extends('layouts.app')

@section('title', 'Virtual Brand Revenue Split & Settlement')
@section('subtitle', 'Pemisahan Pendapatan Otomatis: Dapur Komersial (60%), Pemilik IP Virtual Brand (25%), Platform (15%)')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Pesanan Selesai Terkirim & Settlement Bagi Hasil</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="p-3">Order</th>
                        <th class="p-3">Dapur</th>
                        <th class="p-3">Total (100%)</th>
                        <th class="p-3">Dapur (60%)</th>
                        <th class="p-3">Brand IP (25%)</th>
                        <th class="p-3">Platform (15%)</th>
                        <th class="p-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($deliveredOrders as $d)
                        @php
                            $total = $d->meal_cost_idr;
                            $kitchen = (int) round($total * 0.60);
                            $brand = (int) round($total * 0.25);
                            $plat = max(0, $total - $kitchen - $brand);
                        @endphp
                        <tr class="hover:bg-slate-800/30">
                            <td class="p-3 font-mono font-bold text-orange-400 text-xs">{{ $d->order_code }}</td>
                            <td class="p-3 text-xs">{{ optional($d->kitchen)->name ?? '-' }}</td>
                            <td class="p-3 font-mono text-xs font-bold text-slate-100">Rp {{ number_format($total) }}</td>
                            <td class="p-3 font-mono text-xs text-blue-400">Rp {{ number_format($kitchen) }}</td>
                            <td class="p-3 font-mono text-xs text-amber-400">Rp {{ number_format($brand) }}</td>
                            <td class="p-3 font-mono text-xs text-emerald-400">Rp {{ number_format($plat) }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 uppercase">
                                    LUNAS
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">Belum ada pesanan terselesaikan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $deliveredOrders->links() }}
        </div>
    </div>
</div>
@endsection
