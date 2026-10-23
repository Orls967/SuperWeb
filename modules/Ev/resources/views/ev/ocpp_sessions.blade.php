@extends('layouts.app')

@section('title', 'EV OCPP Smart Charging Sessions')
@section('subtitle', 'Manajemen Sesi Pengisian Daya Cerdas OCPP 2.0.1, Dynamic Load Balancing & Settle Billing')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Inisiasi Sesi OCPP Baru</h3>
            <form action="{{ route('ev.smart-grid.start-ocpp') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Pilih Charger SPKLU</label>
                    <select name="charger_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($chargers as $c)
                            <option value="{{ $c->id }}">{{ $c->charger_code }} ({{ $c->type }} - {{ $c->max_kw }}kW) [{{ $c->status }}]</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">ID Kendaraan Listrik</label>
                    <input type="number" name="vehicle_id" placeholder="misal: 101" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Hold Deposit (IDR Integer)</label>
                    <input type="number" name="deposit_idr" value="50000" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tarif / kWh (IDR Integer)</label>
                    <input type="number" name="tariff_idr" value="2450" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm">
                    Mulai Sesi Pengisian OCPP
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Sesi Pengisian Sedang Berlangsung / Selesai</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Kode Sesi</th>
                            <th class="p-3">Charger</th>
                            <th class="p-3">Energi (Wh)</th>
                            <th class="p-3">Biaya (IDR)</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($sessions as $s)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono font-bold text-emerald-400 text-xs">{{ $s->session_code }}</td>
                                <td class="p-3 text-xs">{{ optional($s->charger)->charger_code ?? '-' }}</td>
                                <td class="p-3 font-mono text-xs">{{ number_format($s->energy_wh) }} Wh</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($s->total_cost_idr) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded {{ $s->status === 'charging' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                                        {{ $s->status }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    @if($s->status === 'charging')
                                        <form action="{{ route('ev.smart-grid.settle-tou') }}" method="POST" class="inline-flex gap-1">
                                            @csrf
                                            <input type="hidden" name="session_id" value="{{ $s->id }}">
                                            <input type="hidden" name="energy_wh" value="32500">
                                            <button type="submit" class="px-2.5 py-1 bg-violet-600 hover:bg-violet-500 text-white rounded-lg text-xs font-semibold">
                                                Settle 32.5 kWh
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">Lunas</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">Belum ada sesi pengisian daya.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $sessions->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
