@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Dynamic Expiry Markdown Pricing & Shrinkage</h2>
            <p class="mt-1 text-sm text-gray-400">Penyesuaian Harga Dinamis Barang Mendekati Kedaluwarsa & Rekonsiliasi Susut Fisik</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Markdown Dynamic Pricing Form -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-4">Eksekusi Dynamic Expiry Markdown</h3>
                    <form action="{{ route('ret.pricing.markdown') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Pilih SKU Produk</label>
                            <select name="sku" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                                @foreach($items as $item)
                                    <option value="{{ $item->sku }}">{{ $item->product_name }} ({{ $item->sku }}) - Rp {{ number_format($item->map_price_minor, 0, ',', '.') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Sisa Hari Kedaluwarsa (Days Remaining)</label>
                            <input type="number" name="days_remaining" value="2" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded transition">
                            Terapkan Dynamic Markdown
                        </button>
                    </form>
                </div>

                <!-- Shrinkage Reconciliation Form -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-md font-medium text-white mb-4">Rekonsiliasi Susut Fisik (Shrinkage Write-off)</h3>
                    <form action="{{ route('ret.pricing.shrinkage') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Pilih SKU Produk</label>
                            <select name="sku" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                                @foreach($items as $item)
                                    <option value="{{ $item->sku }}">{{ $item->product_name }} (Stok Sistem: {{ $item->stock_available }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300 mb-1">Jumlah Fisik Terhitung Aktual</label>
                            <input type="number" name="physical_count" value="45" class="w-full bg-gray-800 border border-gray-600 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2 px-4 bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded transition">
                            Rekonsiliasi & Write-off Susut
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
