@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>💰</span> Minerba PNBP Royalty Calculation &amp; Accrual
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 94: Indonesian statutory mining royalty calculation based on verified tonnage and HMA benchmark prices.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('mining.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Mining Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Calculate Monthly Royalty</h3>
                    <form action="{{ route('mining.operations.pnbp-royalty.calculate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Mining Concession Site</label>
                            <select name="site_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->commodity }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Royalty Accrual Period (YYYY-MM)</label>
                            <input type="text" name="period" value="{{ date('Y-m') }}" maxlength="7" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Total Verified Production (Tons)</label>
                            <input type="number" step="0.1" name="total_production_ton" value="25000" min="1" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">HMA Benchmark Price (IDR / Ton)</label>
                            <input type="number" name="commodity_benchmark_price_idr" value="1250000" min="1000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Statutory Royalty Rate (%)</label>
                            <input type="number" step="0.01" name="royalty_rate_percentage" value="10.00" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 font-semibold rounded text-white text-sm transition">
                            Accrue PNBP Royalty Ledger
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Statutory Royalty Accrual History</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Period</th>
                                    <th class="p-3">Site</th>
                                    <th class="p-3">Production (T)</th>
                                    <th class="p-3">HMA (IDR)</th>
                                    <th class="p-3">Rate</th>
                                    <th class="p-3">Royalty Due (IDR)</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 font-mono">
                                @forelse($royalties as $r)
                                    <tr class="hover:bg-gray-800/50">
                                        <td class="p-3 font-semibold text-white">{{ $r->period }}</td>
                                        <td class="p-3">{{ $r->site->name ?? substr($r->site_id, 0, 8) }}</td>
                                        <td class="p-3 text-cyan-400">{{ number_format($r->total_production_ton, 1) }}</td>
                                        <td class="p-3">IDR {{ number_format($r->commodity_benchmark_price_idr) }}</td>
                                        <td class="p-3">{{ number_format($r->royalty_rate_percentage, 2) }}%</td>
                                        <td class="p-3 text-emerald-400 font-bold">IDR {{ number_format($r->royalty_due_idr) }}</td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
                                                {{ $r->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-gray-500">No royalty calculations recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $royalties->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
