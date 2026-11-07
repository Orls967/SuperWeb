@extends('layouts.app')

@section('content')
<div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Papan Front Desk Operasional</h1>
            <p class="text-sm text-slate-500">Kedatangan, keberangkatan, dan status kamar real-time.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 gap-2">
            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                Business Date: Aktif
            </span>
        </div>
    </div>

    <!-- Alert / Toast -->
    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200" role="alert" aria-live="polite">
            <div class="flex">
                <div class="text-sm font-medium text-emerald-800">{{ session('success') }}</div>
            </div>
        </div>
    @endif

    <!-- KPI Summary Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Arrivals Hari Ini</dt>
            <dd class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $arrivals->count() }}</dd>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Departures Hari Ini</dt>
            <dd class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $departures->count() }}</dd>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">In-House Guests</dt>
            <dd class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ $inHouse->count() }}</dd>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200 shadow-sm">
            <dt class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Kamar Siap</dt>
            <dd class="mt-1 text-2xl font-bold text-emerald-600 tabular-nums">{{ $rooms->where('status', 'available')->count() }}</dd>
        </div>
    </div>

    <!-- Tab Section: Arrivals List -->
    <div class="bg-white shadow-sm rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-4 py-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Antrean Kedatangan (Arrivals) Hari Ini</h2>
            <span class="text-xs text-slate-400">Total: {{ $arrivals->count() }} reservasi</span>
        </div>
        @if($arrivals->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500">Tidak ada jadwal kedatangan tamu untuk hari ini.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-700">
                        <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left font-semibold">Kode Reservasi</th>
                            <th class="px-3 py-3.5 text-left font-semibold">Nama Tamu</th>
                            <th class="px-3 py-3.5 text-left font-semibold">Kanal</th>
                            <th class="px-3 py-3.5 text-right font-semibold">Tarif Malam</th>
                            <th class="px-3 py-3.5 text-center font-semibold">Aksi Cepat Check-In</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($arrivals as $res)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 font-mono font-medium text-slate-900">{{ $res->reservation_code }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-slate-700">{{ $res->guest_name }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-slate-500">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ strtoupper($res->channel ?? 'WEB') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-right tabular-nums font-semibold text-slate-900">Rp {{ number_format((float)$res->room_rate, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <form method="POST" action="{{ route('hotel.front_desk.check_in') }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="reservation_id" value="{{ $res->id }}">
                                        <select name="room_id" class="rounded-md border-slate-300 text-xs py-1">
                                            @foreach($rooms->where('status', 'available') as $rm)
                                                <option value="{{ $rm->id }}">Kamar {{ $rm->room_number }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-sky-500">
                                            Check-In & Terbitkan Kunci
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Papan Status Fisik Kamar (Room Grid) -->
    <div class="bg-white shadow-sm rounded-lg border border-slate-200 p-6">
        <h2 class="text-base font-semibold text-slate-900 mb-4">Peta Status Kamar Properti (Teks & Warna)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
            @foreach($rooms as $rm)
                @php
                    $statusBadge = match($rm->status) {
                        'occupied' => 'bg-amber-100 text-amber-800 border-amber-300',
                        'cleaning' => 'bg-sky-100 text-sky-800 border-sky-300',
                        'out_of_order' => 'bg-rose-100 text-rose-800 border-rose-300',
                        default => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                    };
                    $statusLabel = match($rm->status) {
                        'occupied' => 'Terhuni',
                        'cleaning' => 'Dibersihkan',
                        'out_of_order' => 'Rusak (OOO)',
                        default => 'Tersedia',
                    };
                @endphp
                <div class="p-3 rounded-md border text-center {{ $statusBadge }}">
                    <div class="font-bold text-lg text-slate-900">{{ $rm->room_number }}</div>
                    <div class="text-xs font-medium uppercase tracking-wider mt-1">{{ $statusLabel }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
