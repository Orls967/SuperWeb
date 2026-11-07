@extends('layouts.app')

@section('content')
<div class="py-6 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="border-b border-slate-200 pb-4">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Portal Menginap Tamu (Guest Stay Portal)</h1>
        <p class="text-sm text-slate-500">Informasi reservasi, rincian kamar, dan kunci kamar digital.</p>
    </div>

    @if($reservation === null)
        <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-slate-500">
            Anda belum memiliki reservasi aktif saat ini.
        </div>
    @else
        <!-- Rincian Reservasi -->
        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <span class="text-xs text-slate-400 font-mono">Nomor Reservasi</span>
                    <div class="text-xl font-bold text-slate-900">{{ $reservation->reservation_number ?? $reservation->reservation_code }}</div>
                </div>
                <div>
                    <span class="rounded bg-emerald-100 text-emerald-800 px-2.5 py-1 text-xs font-semibold uppercase">
                        {{ $reservation->status }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs text-slate-400">Check-In</span>
                    <div class="font-semibold text-slate-800">{{ $reservation->check_in_date }} (14:00)</div>
                </div>
                <div>
                    <span class="text-xs text-slate-400">Check-Out</span>
                    <div class="font-semibold text-slate-800">{{ $reservation->check_out_date }} (12:00)</div>
                </div>
            </div>

            @if($digitalKey !== null && $digitalKey->status === 'active')
                <!-- Kunci Digital Kamar -->
                <div class="rounded-lg bg-sky-50 p-5 border border-sky-200 text-center space-y-3">
                    <div class="text-xs font-bold text-sky-800 uppercase tracking-wider">Kunci Digital Kamar Aktif</div>
                    <div class="p-4 bg-white inline-block rounded-lg shadow-sm border border-slate-200 font-mono text-xs text-slate-600">
                        [QR-SESSION-LOCK: {{ substr($digitalKey->session_token_hash, 0, 20) }}...]
                    </div>
                    <div class="text-xs text-slate-500">
                        Dekatkan ponsel Anda ke pembaca pintu kamar. Berlaku hingga: {{ $digitalKey->valid_until?->format('d M Y H:i') }}
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
