@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">InsurTech & Claims Autopilot</h2>
            <p class="mt-1 text-sm text-gray-400">Polis Mikro Tertanam Otomatis, Settlement Klaim Instan & Cadangan Premi Ledger</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Products -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Produk Asuransi Mikro</h3>
                    <div class="space-y-3">
                        @forelse($products as $prod)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $prod->name }} ({{ $prod->product_code }})</div>
                                    <div class="text-xs text-gray-400">Premi: Rp {{ number_format($prod->premium_amount_idr) }} • Cover: Rp {{ number_format($prod->max_payout_idr) }}</div>
                                    <div class="text-xs text-indigo-400 mt-1">Trigger: {{ $prod->trigger_event_type }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('insurance.products.issue', $prod) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Beli Polis
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada produk asuransi aktif.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Policies -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Polis Tertanam Aktif</h3>
                    <div class="space-y-2">
                        @forelse($policies as $pol)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">{{ $pol->policy_number }}</div>
                                    <div class="text-xs text-gray-400">Produk: {{ $pol->product?->name }} • Limit: Rp {{ number_format($pol->coverage_limit_idr) }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $pol->status === 'active' ? 'bg-emerald-900 text-emerald-200' : 'bg-blue-900 text-blue-200' }}">
                                        {{ $pol->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada polis aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Claims -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Klaim Autopilot Terkini</h3>
                <div class="space-y-2">
                    @forelse($claims as $clm)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                            <div>
                                <div class="text-white font-medium">{{ $clm->claim_number }}</div>
                                <div class="text-xs text-gray-400">Polis: {{ $clm->policy?->policy_number }} • Payout: Rp {{ number_format($clm->payout_amount_idr) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex px-2 text-xs font-semibold rounded-full bg-emerald-900 text-emerald-200">
                                    {{ $clm->status }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Belum ada klaim diajukan.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
