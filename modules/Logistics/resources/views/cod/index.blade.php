<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">Dashboard COD</h2>
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">Pencairan D+{{ $settlementDays }} ke shipper</span>
        </div>
    </x-slot>

    <div class="py-8 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="p-4 rounded-xl bg-red-950/80 border border-red-500/50 text-red-200 text-sm">{{ $errors->first() }}</div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Di Tangan Driver</div><div class="text-xl font-bold font-mono text-rose-300" id="kpi-collected">Rp {{ number_format($collectedAmount, 0, ',', '.') }}</div><div class="text-[11px] text-slate-500">{{ $counts['collected'] }} resi</div></div>
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Disetor, Menunggu Cair</div><div class="text-xl font-bold font-mono text-amber-300" id="kpi-deposited">Rp {{ number_format($depositedAmount, 0, ',', '.') }}</div><div class="text-[11px] text-slate-500">{{ $counts['deposited'] }} resi</div></div>
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Sudah Dicairkan (Net)</div><div class="text-xl font-bold font-mono text-emerald-300" id="kpi-settled">Rp {{ number_format($settledNet, 0, ',', '.') }}</div><div class="text-[11px] text-slate-500">{{ $counts['settled'] }} resi</div></div>
            <div class="p-4 rounded-2xl bg-slate-800/60 border border-slate-700/60"><div class="text-xs text-slate-400">Pendapatan Fee COD</div><div class="text-xl font-bold font-mono text-cyan-300" id="kpi-fee">Rp {{ number_format($settledFee, 0, ',', '.') }}</div></div>
        </div>

        {{-- Setoran --}}
        <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden" id="driver-cash">
            <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Uang COD di Tangan Driver</h3></div>
            <div class="divide-y divide-slate-700/40">
                @forelse($driverOutstanding as $row)
                    @php($driver = $drivers[$row->driver_id] ?? null)
                    <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs text-slate-300">
                        <div>
                            <div class="font-semibold text-slate-100">{{ $driver?->user?->name }} <span class="font-mono text-amber-300">{{ $driver?->driver_number }}</span></div>
                            <div>{{ $row->shipments }} resi &bull; Rp {{ number_format($row->amount, 0, ',', '.') }} &bull; tertua {{ \Illuminate\Support\Carbon::parse($row->oldest_at)->diffForHumans() }}</div>
                        </div>
                        @if($canDeposit)
                            <form method="POST" action="{{ route('logistics.cod.deposit') }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input type="hidden" name="driver_id" value="{{ $row->driver_id }}">
                                <select name="hub_id" class="bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                    @foreach($hubs as $hub)<option value="{{ $hub->id }}">{{ $hub->name }}</option>@endforeach
                                </select>
                                <input name="amount_idr" type="number" min="1" required placeholder="Uang fisik (Rp)" class="w-40 bg-slate-900 border-slate-700 text-slate-100 rounded-lg text-xs">
                                <button class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Terima Setoran</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-slate-400">Tidak ada uang COD di tangan driver.</div>
                @endforelse
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Menunggu Pencairan</h3></div>
                <ul class="divide-y divide-slate-700/40 text-xs text-slate-300">
                    @forelse($pendingSettlement as $c)
                        <li class="px-5 py-3 flex justify-between gap-2"><span class="font-mono text-amber-300">{{ $c->shipment?->tracking_number }}</span><span>Rp {{ number_format($c->amount_idr, 0, ',', '.') }}</span><span class="text-slate-500">cair {{ $c->deposited_at->copy()->addDays($settlementDays)->format('d/m') }}</span></li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-400">Tidak ada.</li>
                    @endforelse
                </ul>
            </section>
            <section class="bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="p-5 border-b border-slate-700/50"><h3 class="text-base font-bold text-slate-100">Aktivitas Terbaru</h3></div>
                <ul class="divide-y divide-slate-700/40 text-xs text-slate-300">
                    @forelse($recent as $c)
                        <li class="px-5 py-3 flex justify-between gap-2"><span class="font-mono text-amber-300">{{ $c->shipment?->tracking_number }}</span><span>{{ $c->status }}</span><span>Rp {{ number_format($c->amount_idr, 0, ',', '.') }}</span></li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-400">Belum ada.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
