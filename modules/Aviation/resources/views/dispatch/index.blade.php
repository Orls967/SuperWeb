@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationDispatchApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-teal-500/20 text-teal-400 border border-teal-500/30">
                        Flight Dispatch Release Console (375px Ready)
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Algoritma A2 & A3 Certified</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Pelepasan Penerbangan & Weight and Balance</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Verifikasi kelaikan udara (A2), amplop pusat gravitasi (A3), bahan bakar avtur cadangan, dan izin keberangkatan.
                </p>
            </div>
            <div>
                <a href="{{ route('aviation.ops.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Pusat Kontrol OCC
                </a>
            </div>
        </div>

        {{-- Dispatch Queue --}}
        <div class="space-y-4">
            <h2 class="text-base sm:text-lg font-bold text-white">Antrean Rilis Penerbangan Hari Ini</h2>

            @if($flights->isEmpty())
                <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-12 text-center">
                    <span class="text-4xl block mb-2">📋</span>
                    <h3 class="text-sm font-semibold text-white">Tidak Ada Penerbangan Dalam Antrean Dispatch</h3>
                    <p class="text-xs text-slate-400 mt-1">Semua penerbangan telah dilepas atau belum dijadwalkan.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($flights as $flight)
                        <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3 mb-3">
                                    <span class="font-mono font-black text-lg text-teal-300">{{ $flight->flight_number }}</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                        @if($flight->status === 'airworthy_cleared') bg-sky-500/20 text-sky-400 border border-sky-500/30
                                        @elseif($flight->status === 'departed') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                        @else bg-slate-700 text-slate-300 @endif">
                                        {{ strtoupper($flight->status) }}
                                    </span>
                                </div>
                                <div class="space-y-2 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Rute:</span>
                                        <span class="font-mono text-white font-semibold">{{ $flight->origin->iata_code ?? '---' }} &rarr; {{ $flight->destination->iata_code ?? '---' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Pesawat:</span>
                                        <span class="font-mono text-white">{{ $flight->aircraft->registration_mark ?? 'Belum Ditugaskan' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Jadwal ETD:</span>
                                        <span class="text-white">{{ $flight->departure_time->format('H:i') }} WIB</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400">Bahan Bakar:</span>
                                        <span class="font-mono text-white">{{ number_format($flight->fuel_burn_kg) }} kg</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 pt-3 border-t border-slate-700/60 flex flex-col sm:flex-row gap-2">
                                @if($flight->status === 'planned' || $flight->status === 'slot_secured')
                                    <form action="{{ route('aviation.dispatch.execute', $flight) }}" method="POST" class="w-full">
                                        @csrf
                                        <button type="submit" class="w-full py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-teal-600/30">
                                            Rilis Dispatch (A2/A3)
                                        </button>
                                    </form>
                                @elseif($flight->status === 'airworthy_cleared')
                                    <form action="{{ route('aviation.dispatch.depart', $flight) }}" method="POST" class="w-full">
                                        @csrf
                                        <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-emerald-600/30">
                                            Depart Pesawat &rarr;
                                        </button>
                                    </form>
                                @else
                                    <button disabled class="w-full py-2 bg-slate-800 text-slate-500 rounded-xl text-xs font-semibold cursor-not-allowed border border-slate-700">
                                        Sudah Dilepas
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function aviationDispatchApp() {
    return {};
}
</script>
@endsection

