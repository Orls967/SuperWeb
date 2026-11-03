@extends('layouts.app')

@section('title', 'Fleet TCO & Replacement Optimization')
@section('subtitle', 'Analisis Total Biaya Kepemilikan (TCO), Ambang Batas Downtime & Jadwal Remajakan Armada')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Simulasi Optimasi Penggantian & TCO Armada</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($contracts as $c)
                <div class="p-5 rounded-xl bg-slate-900/60 border border-slate-800 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-mono font-bold text-blue-400 text-sm">{{ $c->contract_number }}</span>
                            <span class="text-xs text-slate-400">{{ $c->units->count() }} Unit Terpasang</span>
                        </div>
                        <p class="text-xs text-slate-400 mb-3">Maks Downtime Diizinkan: {{ $c->max_downtime_hours_per_month }} jam/bln</p>
                    </div>
                    <form action="{{ route('fleet.lifecycle.run-tco') }}" method="POST">
                        @csrf
                        <input type="hidden" name="contract_id" value="{{ $c->id }}">
                        <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg text-xs">
                            Jalankan Analisis TCO & Remajakan
                        </button>
                    </form>
                </div>
            @empty
                <div class="col-span-full p-8 text-center text-slate-500">Tidak ada kontrak aktif untuk dianalisis TCO.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
