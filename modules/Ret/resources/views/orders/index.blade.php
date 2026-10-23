@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Omnichannel Retail Orders & Unified Cart</h2>
            <p class="mt-1 text-sm text-gray-400">Pusat Pesanan Ritel Lintas Kanal (Physical Store, Web App & Marketplace)</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Channels -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Kanal Ritel Aktif ({{ $channels->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($channels as $channel)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $channel->channel_name }}</div>
                                    <div class="text-xs text-gray-400">Tipe: {{ $channel->channel_type }} • Kode: {{ $channel->channel_code }}</div>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded bg-emerald-900 text-emerald-200">{{ $channel->status }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada kanal retail.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Orders -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-3">Pesanan Terbaru ({{ $orders->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($orders as $order)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-mono text-sm">{{ $order->order_number }}</div>
                                    <div class="text-xs text-gray-400">Customer: {{ $order->customer_id }} • Total: Rp {{ number_format($order->total_amount_minor, 0, ',', '.') }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-900 text-indigo-200">{{ $order->status }}</span>
                                    <a href="{{ route('ret.orders.show', $order->id) }}" class="text-xs text-indigo-400 hover:underline">Detail</a>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada pesanan.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
