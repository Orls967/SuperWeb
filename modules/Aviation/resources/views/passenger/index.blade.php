@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="border-b border-slate-800 pb-5">
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-blue-500/20 text-blue-400 border border-blue-500/30">
                Self-Service Passenger Portal
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Tiket Penerbangan Saya</h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Akses aman ke tiket elektronik dan kartu pas naik pesawat (boarding pass). Dilindungi kebijakan anti-IDOR.
            </p>
        </div>

        <div class="space-y-4">
            @if($tickets->isEmpty())
                <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-12 text-center">
                    <span class="text-4xl block mb-2">🎫</span>
                    <h3 class="text-sm font-semibold text-white">Tidak Ada Tiket Ditemukan</h3>
                    <p class="text-xs text-slate-400 mt-1">Anda belum memiliki pemesanan tiket penerbangan yang aktif.</p>
                </div>
            @else
                @foreach($tickets as $ticket)
                    <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sky-400 text-base">{{ $ticket->ticket_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-700 text-slate-300">
                                    {{ $ticket->cabin }}
                                </span>
                            </div>
                            <div class="text-sm font-semibold text-white mt-1">
                                {{ $ticket->flight->flight_number ?? 'Penerbangan' }} •
                                {{ $ticket->flight->origin->iata_code ?? '---' }} &rarr; {{ $ticket->flight->destination->iata_code ?? '---' }}
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                Keberangkatan: {{ $ticket->flight ? $ticket->flight->departure_time->format('d M Y, H:i') : '-' }} WIB
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('aviation.passenger.ticket', $ticket) }}" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-semibold transition-colors shadow-lg shadow-sky-600/30">
                                Lihat Boarding Pass &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection

