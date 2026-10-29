@extends('layouts.app')

@section('title', 'Portal Klien Korporat Hotel')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Portal Korporat & City Ledger</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        @foreach($accounts as $acc)
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-bold text-gray-800">{{ $acc->company_name }}</h2>
            <p class="text-sm text-gray-500 mb-4">Kode Perusahaan: {{ $acc->company_code }}</p>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Batas Kredit:</span>
                    <span class="font-mono font-semibold">IDR {{ number_format($acc->credit_limit_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Saldo Terutang (Outstanding):</span>
                    <span class="font-mono font-semibold text-red-600">IDR {{ number_format($acc->outstanding_balance_idr, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Sisa Limit:</span>
                    <span class="font-mono font-semibold text-green-600">IDR {{ number_format(max(0, $acc->credit_limit_idr - $acc->outstanding_balance_idr), 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
