@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-6 px-4 sm:px-6 lg:px-8" x-data="aviationBookingsApp()">
    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                        Passenger Reservations & PM 89/2015 Refunds
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Fase 177 • Algoritma A4 Certified • Kontrak G7-K26</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">Reservasi Tiket & Refund Penumpang</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    Hold kursi dengan TTL kedaluwarsa 15 menit, penerbitan tiket, proteksi privasi PDP, dan refund bergradien waktu.
                </p>
            </div>
            <div>
                <a href="{{ route('aviation.ops.index') }}" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs sm:text-sm font-semibold border border-slate-700 transition-colors">
                    &larr; Ke Pusat Kontrol OCC
                </a>
            </div>
        </div>

        {{-- Form Pemesanan Tiket 2-Langkah --}}
        <div class="bg-slate-800/60 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <h2 class="text-base sm:text-lg font-bold text-white mb-3">Pesan Kursi Penumpang (Hold & Confirm)</h2>
            <form action="{{ route('aviation.bookings.hold') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Penerbangan</label>
                    <select name="flight_id" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        @foreach($flights as $flight)
                            <option value="{{ $flight->id }}">{{ $flight->flight_number }} ({{ $flight->origin->iata_code ?? '-' }} &rarr; {{ $flight->destination->iata_code ?? '-' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kabin</label>
                    <select name="cabin" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white">
                        <option value="economy">Economy (Y)</option>
                        <option value="business">Business (J)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jumlah Kursi</label>
                    <input type="number" name="seats_count" min="1" max="9" value="1" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Penumpang</label>
                    <input type="text" name="passenger_name" value="Budi Santoso" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white" required>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors shadow-lg shadow-cyan-600/30">
                        Hold Kursi (15 Menit)
                    </button>
                </div>
            </form>
        </div>

        {{-- Tickets Table --}}
        <div class="bg-slate-800/50 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base sm:text-lg font-bold text-white">Daftar Tiket Penumpang Terbit</h2>
                <span class="text-xs text-slate-400">Total: {{ $tickets->count() }} Tiket</span>
            </div>

            @if($tickets->isEmpty())
                <div class="text-center py-12 border border-dashed border-slate-700 rounded-xl">
                    <span class="text-4xl block mb-2">🎫</span>
                    <h3 class="text-sm font-semibold text-white">Belum Ada Tiket Terbit</h3>
                    <p class="text-xs text-slate-400 mt-1">Lakukan hold kursi di atas untuk memulai proses penerbitan tiket.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="bg-slate-900/60 text-slate-400 border-b border-slate-700/60">
                            <tr>
                                <th class="p-3">Nomor Tiket</th>
                                <th class="p-3">Penerbangan</th>
                                <th class="p-3">Kabin</th>
                                <th class="p-3">Total Tarif</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Aksi Refund</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($tickets as $t)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3 font-mono font-bold text-cyan-300">{{ $t->ticket_number }}</td>
                                    <td class="p-3 font-mono">{{ $t->flight->flight_number ?? '-' }}</td>
                                    <td class="p-3 uppercase">{{ $t->cabin }} ({{ $t->fare_class }})</td>
                                    <td class="p-3 font-mono">Rp {{ number_format($t->total_fare_minor / 100, 0, ',', '.') }}</td>
                                    <td class="p-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold
                                            @if($t->status === 'issued') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                            @elseif($t->status === 'refunded') bg-rose-500/20 text-rose-400 border border-rose-500/30
                                            @else bg-slate-700 text-slate-300 @endif">
                                            {{ strtoupper($t->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        @if($t->status === 'issued')
                                            <form action="{{ route('aviation.bookings.refund', $t) }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="reason" value="passenger_request">
                                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-semibold underline">
                                                    Refund (PM 89)
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function aviationBookingsApp() {
    return {};
}
</script>
@endsection

