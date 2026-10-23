@extends('layouts.app')

@section('title', 'Parametric Insurance IoT Oracle Triggers')
@section('subtitle', 'Pemicu Otomatis Klaim Berbasis Telemetri Kendaraan, Cuaca, Sensor IoT & Oracle Eksternal')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Simulasi Trigger Oracle Eksternal</h3>
            <form action="{{ route('insurance.workflow.trigger-parametric') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Polis Aktif Sasaran</label>
                    <select name="policy_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono text-xs">
                        @foreach($activePolicies as $pol)
                            <option value="{{ $pol->id }}">{{ $pol->policy_number }} (Max: Rp {{ number_format($pol->coverage_limit_idr) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Event Oracle ID</label>
                    <input type="text" name="trigger_event_id" value="ORACLE-EVT-FLOOD-JKT-{{ date('Ymd') }}" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100 font-mono text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Jumlah Santunan Diklaim (IDR)</label>
                    <input type="number" name="claim_amount_idr" value="5000000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm">
                    Injeksi Trigger Oracle & Ajukan Klaim
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-3">Model Produk Parametrik Aktif AutoServe</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @forelse($products as $p)
                    <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono text-xs font-bold text-violet-400">{{ $p->product_code }}</span>
                            <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400">PARAMETRIC</span>
                        </div>
                        <p class="font-bold text-slate-200 text-sm mb-1">{{ $p->name }}</p>
                        <p class="text-xs text-slate-400 mb-2">Pemicu: <span class="font-mono text-slate-300">{{ $p->trigger_event_type }}</span></p>
                        <div class="flex justify-between text-xs text-slate-300 pt-2 border-t border-slate-800">
                            <span>Premi: Rp {{ number_format($p->premium_amount_idr) }}</span>
                            <span class="font-bold text-emerald-400">Santunan Max: Rp {{ number_format($p->max_payout_idr) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-4 text-center text-slate-500">Belum ada produk parametrik terdaftar.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
