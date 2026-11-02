@extends('layouts.app')

@section('title', 'Time-of-Use (TOU) Smart Grid Tariffs')
@section('subtitle', 'Dinamika Tarif Puncak (Peak) & Luar Puncak (Off-Peak) dan Kapasitas Trafo SPKLU')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-100">Jadwal & Tarif Time-of-Use (TOU) Grid</h1>
        <a href="{{ route('ev.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-200 bg-slate-800/80 hover:bg-slate-700 rounded-xl border border-slate-700">
            &larr; Dashboard EV
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-amber-400 mb-2">Jam Beban Puncak (Peak Hours: 17:00 - 22:00)</h3>
            <p class="text-3xl font-extrabold text-slate-100 mb-2">Rp 2.450 <span class="text-sm font-normal text-slate-400">/ kWh</span></p>
            <p class="text-xs text-slate-400 leading-relaxed">
                Pada jam beban puncak, permintaan listrik regional tinggi. Pengisian daya cepat dikenakan tarif premium untuk mendorong pengisian daya di luar jam sibuk.
            </p>
        </div>

        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-emerald-400 mb-2">Luar Jam Puncak (Off-Peak: 22:00 - 17:00)</h3>
            <p class="text-3xl font-extrabold text-slate-100 mb-2">Rp 1.650 <span class="text-sm font-normal text-slate-400">/ kWh</span></p>
            <p class="text-xs text-slate-400 leading-relaxed">
                Tarif bersubsidi ramah lingkungan hemat hingga 32% untuk pengisian semalaman (overnight fleet depot charging) dengan emisi marjinal rendah.
            </p>
        </div>
    </div>

    <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
        <h3 class="text-base font-bold text-slate-100 mb-4">Stasiun Pengisian Daya & Kapasitas Trafo</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse($stations as $st)
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800">
                    <p class="font-bold text-slate-200">{{ $st->name }}</p>
                    <p class="text-xs text-slate-400 font-mono">{{ $st->station_code }} - {{ $st->location_type }}</p>
                    <div class="mt-3 flex justify-between text-xs text-slate-300">
                        <span>Kapasitas Trafo</span>
                        <span class="font-bold text-emerald-400">{{ $st->transformer_capacity_kw }} kW</span>
                    </div>
                    <div class="mt-1 flex justify-between text-xs text-slate-300">
                        <span>Total Dispenser</span>
                        <span class="font-bold text-violet-400">{{ $st->chargers->count() }} Unit</span>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-4 text-center text-slate-500">Belum ada stasiun terdaftar.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
