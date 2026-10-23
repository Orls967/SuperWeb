@extends('layouts.app')

@section('title', 'Blind Tender Award & PO Generation')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Blind Tender Awarding</h1>
            <p class="text-sm text-gray-500">Evaluasi lelang tertutup terenkripsi dan penerbitan Purchase Order otomatis.</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
            Encrypted Bidding
        </span>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Tender Unsealing & Scoring</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Total Sealed Bids</span>
                <p class="text-2xl font-bold text-gray-900 mt-1">5 Bids</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Cryptographic Validation</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1">Verified SHA-256</p>
            </div>
            <div class="p-4 bg-gray-50 rounded border">
                <span class="text-xs text-gray-500 font-semibold uppercase">Awarded Vendor</span>
                <p class="text-2xl font-bold text-indigo-600 mt-1">Lowest Valid Offer</p>
            </div>
        </div>
    </div>
</div>
@endsection
