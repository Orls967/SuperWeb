@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Dark-Store Quick Commerce Micro-Fulfillment</h2>
            <p class="mt-1 text-sm text-gray-400">Pengelolaan Dark Store, Pemenuhan Cepat 30 Menit & SLA Kepatuhan</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                <h3 class="text-md font-medium text-white mb-4">Alokasi Pick-Pack Dark Store Baru</h3>
                <form action="{{ route('ret.darkstore.allocate') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Dark Store ID</label>
                        <input type="text" name="dark_store_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="DS-JAKSEL-01">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-300 mb-1">Customer ID</label>
                        <input type="text" name="customer_id" required class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="CUST-8812">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded transition">
                            Alokasikan Pesanan Dark Store
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 space-y-2">
                <h3 class="text-md font-medium text-white mb-2">Pesanan Quick Commerce ({{ $orders->count() }})</h3>
                @forelse($orders as $qc)
                    <div class="p-3 bg-gray-700/50 rounded border border-gray-600 flex justify-between items-center text-xs">
                        <div>
                            <span class="font-mono text-white font-medium">{{ $qc->order_code }}</span>
                            <span class="text-gray-400 ml-2">Store: {{ $qc->dark_store_id }} • Waktu Pick: {{ $qc->picking_duration_seconds }}s</span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $qc->status }}</span>
                    </div>
                @empty
                    <div class="text-gray-400 text-sm">Belum ada pesanan dark store.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
