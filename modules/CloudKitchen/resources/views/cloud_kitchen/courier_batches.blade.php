@extends('layouts.app')

@section('title', 'Cloud Kitchen Multi-Drop Courier Batching')
@section('subtitle', 'Konsolidasi Titik Antar Katering ke Rute Kurir Tunggal untuk Efisiensi Waktu & Biaya Logistik')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Pesanan Siap Diantar (Multi-Drop)</h3>
        <form action="{{ route('cloud_kitchen.operations.batch-couriers') }}" method="POST">
            @csrf
            <div class="overflow-x-auto mb-4">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Pilih</th>
                            <th class="p-3">Order</th>
                            <th class="p-3">Dapur</th>
                            <th class="p-3">Slot</th>
                            <th class="p-3">Biaya Porsi</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($readyOrders as $ro)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3">
                                    <input type="checkbox" name="order_ids[]" value="{{ $ro->id }}" checked class="rounded bg-slate-900 border-slate-700 text-orange-500">
                                </td>
                                <td class="p-3 font-mono font-bold text-orange-400 text-xs">{{ $ro->order_code }}</td>
                                <td class="p-3 text-xs">{{ optional($ro->kitchen)->name ?? '-' }}</td>
                                <td class="p-3 uppercase text-xs">{{ $ro->meal_slot }}</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($ro->meal_cost_idr) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-amber-500/20 text-amber-400 uppercase">
                                        {{ $ro->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">Tidak ada pesanan siap diantar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($readyOrders->isNotEmpty())
                <button type="submit" class="px-5 py-2.5 bg-orange-600 hover:bg-orange-500 text-white font-bold rounded-xl text-sm">
                    Buat Batch Pengantaran Multi-Drop
                </button>
            @endif
        </form>
    </div>
</div>
@endsection
