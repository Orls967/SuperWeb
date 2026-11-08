@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <a href="{{ route('aviation.passenger.index') }}" class="text-xs sm:text-sm text-sky-400 hover:text-sky-300 font-semibold">
                &larr; Kembali ke Daftar Tiket
            </a>
            <span class="text-xs text-slate-400 font-mono">IDOR Verified</span>
        </div>

        {{-- Boarding Pass Card (375px Ready) --}}
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-700/80 pb-4">
                <div>
                    <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold block">Boarding Pass Elektronik</span>
                    <h2 class="text-xl sm:text-2xl font-black text-white font-mono mt-0.5">{{ $ticket->flight->flight_number ?? 'FLIGHT' }}</h2>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    {{ strtoupper($ticket->status) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-[11px] text-slate-400 block">ASAL</span>
                    <span class="text-2xl sm:text-3xl font-black font-mono text-white">{{ $ticket->flight->origin->iata_code ?? 'CGK' }}</span>
                    <span class="text-xs text-slate-400 block">{{ $ticket->flight->origin->city ?? 'Jakarta' }}</span>
                </div>
                <div class="text-right">
                    <span class="text-[11px] text-slate-400 block">TUJUAN</span>
                    <span class="text-2xl sm:text-3xl font-black font-mono text-white">{{ $ticket->flight->destination->iata_code ?? 'DPS' }}</span>
                    <span class="text-xs text-slate-400 block">{{ $ticket->flight->destination->city ?? 'Bali' }}</span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 border-t border-b border-slate-700/80 py-4 text-xs">
                <div>
                    <span class="text-slate-400 block">Kabin</span>
                    <span class="font-bold text-white uppercase">{{ $ticket->cabin }} ({{ $ticket->fare_basis ?? $ticket->fare_class ?? 'STD' }})</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Waktu Keberangkatan</span>
                    <span class="font-bold text-white font-mono">{{ $ticket->flight ? $ticket->flight->departure_time->format('H:i') : '--:--' }}</span>
                </div>
                <div class="text-right">
                    <span class="text-slate-400 block">Nomor Kursi</span>
                    <span class="font-bold text-sky-400 font-mono">{{ $ticket->seat_assignment ?? $ticket->seat_number ?? 'PILIH DI GATE' }}</span>
                </div>
            </div>

            {{-- Barcode Simulation --}}
            <div class="bg-white p-4 rounded-xl text-center">
                <div class="font-mono text-[10px] tracking-widest text-slate-900 mb-1">
                    {{ $ticket->ticket_number }}
                </div>
                <div class="h-10 bg-slate-900 mx-auto rounded flex items-center justify-around px-2">
                    <div class="w-1 h-8 bg-white"></div>
                    <div class="w-2 h-8 bg-white"></div>
                    <div class="w-0.5 h-8 bg-white"></div>
                    <div class="w-1.5 h-8 bg-white"></div>
                    <div class="w-3 h-8 bg-white"></div>
                    <div class="w-1 h-8 bg-white"></div>
                    <div class="w-2 h-8 bg-white"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
