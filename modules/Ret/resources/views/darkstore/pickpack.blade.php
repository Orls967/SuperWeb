@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Stasiun Pick & Pack Dark Store</h2>
            <p class="mt-1 text-sm text-gray-400">Monitoring Kecepatan Picking & Kepatuhan SLA Pengantaran</p>

            <div class="mt-6 space-y-3">
                @forelse($orders as $qc)
                    <div class="p-4 bg-gray-700/50 rounded-lg border border-gray-600 flex justify-between items-center text-xs">
                        <div>
                            <span class="font-mono text-white font-medium">{{ $qc->order_code }}</span>
                            <div class="text-gray-400 mt-1">Dark Store: {{ $qc->dark_store_id }} • Pelanggan: {{ $qc->customer_id }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-emerald-400 font-bold">Durasi Pick: {{ $qc->picking_duration_seconds }} detik</span>
                            <div class="text-gray-400">{{ $qc->placed_at }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Tidak ada antrian pick-pack aktif.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
