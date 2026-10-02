<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-slate-100 leading-tight">Laporan Margin Shipment</h2></x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-3 gap-4">
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Pendapatan Diakui</div><div class="text-xl font-bold font-mono text-emerald-300" id="total-revenue">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div></div>
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Biaya Carrier</div><div class="text-xl font-bold font-mono text-rose-300" id="total-cost">Rp {{ number_format($totalCost, 0, ',', '.') }}</div></div>
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Margin</div><div class="text-xl font-bold font-mono text-cyan-300" id="total-margin">Rp {{ number_format($totalMargin, 0, ',', '.') }}</div></div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px]"><tr><th class="px-4 py-3">Resi</th><th class="px-4 py-3">Pendapatan</th><th class="px-4 py-3">Biaya Carrier</th><th class="px-4 py-3">Margin</th><th class="px-4 py-3">%</th></tr></thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($rows as $r)
                        <tr><td class="px-4 py-3 font-mono text-amber-300">{{ $r['shipment']->tracking_number }}</td><td class="px-4 py-3">Rp {{ number_format($r['revenue_idr'], 0, ',', '.') }}</td><td class="px-4 py-3">Rp {{ number_format($r['carrier_cost_idr'], 0, ',', '.') }}</td><td class="px-4 py-3 {{ $r['margin_idr'] < 0 ? 'text-rose-300' : 'text-emerald-300' }}">Rp {{ number_format($r['margin_idr'], 0, ',', '.') }}</td><td class="px-4 py-3">{{ $r['margin_pct'] !== null ? $r['margin_pct'].'%' : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada pendapatan yang diakui.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
