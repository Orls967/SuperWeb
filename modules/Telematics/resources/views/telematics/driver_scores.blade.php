@extends('layouts.app')

@section('title', 'Telematics Driver Behavior & Safety Index')
@section('subtitle', 'Skor Keselamatan Pengemudi, Analisis Pengereman Keras, dan Pelanggaran Kecepatan')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-100">Index Keselamatan Pengemudi Armada</h1>
        <a href="{{ route('telematics.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-200 bg-slate-800/80 hover:bg-slate-700 rounded-xl border border-slate-700">
            &larr; Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($scores as $s)
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-mono text-sm font-bold text-violet-400">Unit VEH-{{ $s['vehicle_id'] }}</span>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg {{ $s['risk_tier'] === 'LOW' ? 'bg-emerald-500/20 text-emerald-400' : ($s['risk_tier'] === 'MODERATE' ? 'bg-amber-500/20 text-amber-400' : 'bg-rose-500/20 text-rose-400') }}">
                        Risk: {{ $s['risk_tier'] }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="text-4xl font-extrabold text-slate-100">{{ $s['safety_score'] }}</span>
                    <span class="text-xs text-slate-400">/ 100 Skor Safety</span>
                </div>
                <div class="space-y-2 text-xs text-slate-300">
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">Pelanggaran Overspeed (>100 km/h)</span>
                        <span class="font-bold text-rose-400">{{ $s['overspeed_events'] }} kali</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-800">
                        <span class="text-slate-400">Akselerasi / RPM Tinggi (>4500 RPM)</span>
                        <span class="font-bold text-amber-400">{{ $s['harsh_events'] }} kali</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-400">Total Sampel Telemetri</span>
                        <span class="font-mono text-slate-200">{{ $s['tick_count'] }} ticks</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center text-slate-500 bg-slate-800/20 rounded-2xl border border-slate-800">
                Belum ada data skor keselamatan pengemudi.
            </div>
        @endforelse
    </div>
</div>
@endsection
