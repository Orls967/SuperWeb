@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Wealth" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Robo-Advisor Wealth Management & Treasury Yield</h2>
            <p class="mt-1 text-sm text-gray-400">Alokasi Portofolio Otomatis, Guardrail Dana Darurat 2x Pengeluaran & Auto-Invest Surplus</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Profiles -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Profil Risiko & Dana Darurat</h3>
                    <div class="space-y-3">
                        @forelse($profiles as $prof)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-medium">User #{{ $prof->user_id }} ({{ strtoupper($prof->risk_profile) }})</div>
                                    <div class="text-xs text-gray-400">Baseline Belanja: Rp {{ number_format($prof->monthly_spend_baseline_idr) }}/bln</div>
                                    <div class="text-xs text-emerald-400 mt-1">Target Dana Darurat: Rp {{ number_format($prof->emergency_fund_target_idr) }}</div>
                                </div>
                                <div>
                                    <form action="{{ route('wealth.profiles.invest', $prof) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="wallet_balance_idr" value="50000000">
                                        <button type="submit" class="py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded transition">
                                            Eksekusi Auto-Invest
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada profil Robo-Advisor.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Plans & Holdings -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Rencana Alokasi Portofolio</h3>
                    <div class="space-y-2">
                        @forelse($plans as $plan)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">Alokasi Profil #{{ $plan->profile_id }}</div>
                                <div class="mt-2 text-xs text-gray-300 space-y-1">
                                    <div class="flex justify-between"><span>Reksadana:</span> <span class="font-semibold text-cyan-400">{{ $plan->allocation_mutual_funds_pct }}%</span></div>
                                    <div class="flex justify-between"><span>Emas Digital:</span> <span class="font-semibold text-amber-400">{{ $plan->allocation_gold_pct }}%</span></div>
                                    <div class="flex justify-between"><span>Kripto:</span> <span class="font-semibold text-indigo-400">{{ $plan->allocation_crypto_pct }}%</span></div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada rencana alokasi.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
