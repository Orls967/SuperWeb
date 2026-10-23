@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>⚖️</span> 14-Day Dispute Window & Escrow Hold Release
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Regulatory compliance, dispute clearing windows, and automated tax withholding settlement.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.artist.royalties') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Creator Royalties
                    </a>
                </div>
            </div>

            <div class="p-5 bg-gray-900/80 rounded-lg border border-gray-700 text-xs text-gray-300 space-y-3">
                <p>All streaming royalties and event revenue splits are subjected to a mandatory 14-calendar-day settlement escrow hold before automated net disbursement to registered bank accounts.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="p-3 bg-gray-800 rounded border border-gray-700">
                        <span class="text-cyan-400 font-bold block mb-1">Step 1: Ingestion</span>
                        <span>DSP stream telemetry and bar POS sales ingested idempotently into ledger.</span>
                    </div>
                    <div class="p-3 bg-gray-800 rounded border border-gray-700">
                        <span class="text-yellow-400 font-bold block mb-1">Step 2: 14-Day Hold</span>
                        <span>Protects against copyright claims, chargebacks, and audit adjustments.</span>
                    </div>
                    <div class="p-3 bg-gray-800 rounded border border-gray-700">
                        <span class="text-emerald-400 font-bold block mb-1">Step 3: Payout</span>
                        <span>PPh 23/21 withholding tax deducted and net funds transferred.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
