@extends('layouts.app')

@section('title', 'Kalkulator Tawarruq FX & Simulasi Trade Finance')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6">
        <div class="text-xs text-gray-500 mb-1"><a href="{{ route('syariah.trade.index') }}" class="hover:underline">Trade Finance</a> / Kalkulator</div>
        <h1 class="text-2xl font-bold text-gray-900">Kalkulator Tawarruq FX & Simulasi Margin</h1>
        <p class="text-xs text-gray-500 mt-1">Simulasi konversi valuta asing syariah berbasis pembelian komoditas riil tanpa bunga ribawi</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-8">
        <form method="GET" action="{{ route('syariah.trade.calculator') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Mata Uang Sumber</label>
                    <input type="text" name="source_currency" value="{{ $sourceCurrency }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Nominal Pokok Sumber (Minor Cents)</label>
                    <input type="number" name="principal_idr" value="{{ $principalIdr }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Mata Uang Target</label>
                    <input type="text" name="target_currency" value="{{ $targetCurrency }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Kurs Spot (Bps / Minor Unit)</label>
                    <input type="number" name="spot_rate_bps" value="{{ $spotRateBps }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Tenor Tangguh (Hari)</label>
                    <input type="number" name="tenor_days" value="{{ $tenorDays }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Tingkat Margin Tahunan (Bps)</label>
                    <input type="number" name="markup_rate_bps" value="{{ $markupRateBps }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Biaya Broker Bursa Komoditas (Minor Cents)</label>
                    <input type="number" name="broker_fee_idr" value="{{ $brokerFeeIdr }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs focus:border-purple-500 focus:ring-purple-500">
                </div>
            </div>
            <div class="pt-3">
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-md shadow-sm transition">
                    Hitung Simulasi Tawarruq
                </button>
            </div>
        </form>
    </div>

    <!-- Output Simulation -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Hasil Estimasi Eksekusi Tawarruq</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <span class="text-gray-500">Target Valas Diterima:</span>
                <p class="font-bold text-emerald-600 text-base mt-1">{{ $targetCurrency }} {{ number_format($targetReceived / 100, 2) }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <span class="text-gray-500">Margin Laba Tangguh:</span>
                <p class="font-bold text-gray-900 text-base mt-1">Rp {{ number_format($markupProfit / 100, 2) }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <span class="text-gray-500">Total Harga Jual Tangguh:</span>
                <p class="font-bold text-purple-700 text-base mt-1">Rp {{ number_format($totalDeferredPrice / 100, 2) }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <span class="text-gray-500">Kurs Efektif:</span>
                <p class="font-bold text-gray-900 text-base mt-1">Rp {{ number_format($effectiveRate / 100, 2) }}</p>
            </div>
        </div>
        <div class="mt-4 p-3 bg-purple-50 rounded border border-purple-100 text-xs text-purple-900">
            <strong>Catatan Kepatuhan Syariah:</strong> Transaksi ini bebas riba, di mana nasabah membeli komoditas bursa secara cicilan/tangguh, kemudian menguasakan agen independen untuk menjualnya secara tunai di bursa komoditas internasional dalam valuta target.
        </div>
    </div>
</div>
@endsection
