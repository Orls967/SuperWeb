@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Real-World Asset (RWA) Tokenization</h2>
            <p class="mt-1 text-sm text-gray-400">Fraksionalisasi Aset Riil, Distribusi Yield Dividen Harian & Pembukuan Reserve Multi-Ledger</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Assets -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Token Aset Terbit</h3>
                    <div class="space-y-3">
                        @forelse($assets as $asset)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $asset->name }} ({{ $asset->token_symbol }})</div>
                                    <div class="text-xs text-gray-400">Total Supply: {{ number_format($asset->total_supply_tokens) }} token • Harga: Rp {{ number_format($asset->token_price_idr) }}</div>
                                    <div class="text-xs text-indigo-400 mt-1">Valuasi: Rp {{ number_format($asset->appraisal_value_idr) }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('rwa.assets.distribute', $asset) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="revenue_pool_idr" value="5000000">
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Bagi Hasil Dividen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada token RWA diterbitkan.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Holdings -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Kepemilikan Investor</h3>
                    <div class="space-y-2">
                        @forelse($holdings as $holding)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">Investor #{{ $holding->user_id }}</div>
                                    <div class="text-xs text-gray-400">Aset: {{ $holding->asset?->name }} ({{ $holding->asset?->token_symbol }})</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-white font-bold text-sm">{{ number_format($holding->token_balance) }} Token</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada pemegang token RWA.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Dividends -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Distribusi Dividen Terkini</h3>
                <div class="space-y-2">
                    @forelse($dividends as $div)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                            <div>
                                <div class="text-white font-medium">Dividen {{ $div->asset?->token_symbol }} • {{ $div->distribution_date?->toDateString() }}</div>
                                <div class="text-xs text-gray-400">Pool: Rp {{ number_format($div->total_revenue_pool_idr) }} • Terdistribusi: Rp {{ number_format($div->distributed_idr) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-400">Rounding Reserve: Rp {{ number_format($div->rounding_reserve_idr) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada dividen dibagikan.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
