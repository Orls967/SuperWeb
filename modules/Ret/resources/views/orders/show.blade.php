@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Detail Pesanan: {{ $order->order_number }}</h2>
                    <p class="mt-1 text-sm text-gray-400">Customer: {{ $order->customer_id }} • Status: {{ $order->status }}</p>
                </div>
                <a href="{{ route('ret.orders.index') }}" class="text-xs text-gray-400 hover:text-white">&larr; Kembali</a>
            </div>

            <div class="mt-6 p-6 bg-gray-700/50 rounded-lg">
                <div class="text-white text-lg font-bold">Total Pembayaran: Rp {{ number_format($order->total_amount_minor, 0, ',', '.') }}</div>
                <div class="text-xs text-gray-400 mt-1">Dibuat pada: {{ $order->created_at }}</div>
            </div>

            @if($order->status === 'PAID')
                <div class="mt-6 bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-4">Pengambilan Pesanan di Click & Collect Locker</h3>
                    <form action="{{ route('ret.orders.pickup') }}" method="POST" class="flex gap-4 items-end">
                        @csrf
                        <input type="hidden" name="order_number" value="{{ $order->order_number }}">
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Locker ID</label>
                            <input type="text" name="locker_id" value="LCK-MALL-01" class="bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">OTP Token</label>
                            <input type="text" name="otp_token" required class="bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm" placeholder="8821">
                        </div>
                        <button type="submit" class="py-2 px-6 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded transition">
                            Konfirmasi Pengambilan
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
