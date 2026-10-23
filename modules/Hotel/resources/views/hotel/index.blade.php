@extends('layouts.app')

@section('content')
    <x-enterprise.module-dashboard module="Hotel" />

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <h2 class="text-xl font-semibold text-white">Hotel, Reservasi & Folio Operations</h2>
            <p class="mt-1 text-sm text-gray-400">Manajemen Properti, Kamar, Reservasi & Folio Settlement Terintegrasi Ledger</p>

            @if(session('success'))
                <div class="mt-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Properties & Rooms -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Properti & Kamar</h3>
                    <div class="space-y-2">
                        @forelse($rooms as $room)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                                <div>
                                    <div class="text-white font-medium">{{ $room->room_number }} ({{ $room->room_type }})</div>
                                    <div class="text-xs text-gray-400">Rate: Rp {{ number_format($room->base_rate_idr) }}/malam</div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex px-2 text-xs font-semibold rounded-full {{ $room->status === 'available' ? 'bg-emerald-900 text-emerald-200' : 'bg-amber-900 text-amber-200' }}">
                                        {{ $room->status }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada data kamar.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Reservations & Settlement -->
                <div class="bg-gray-700/50 p-4 rounded-lg">
                    <h3 class="text-md font-medium text-gray-200 mb-3">Reservasi Aktif & Folio Settlement</h3>
                    <div class="space-y-3">
                        @forelse($reservations as $reservation)
                            <div class="p-3 bg-gray-800 rounded border border-gray-600">
                                <div class="text-white font-medium">{{ $reservation->reservation_number }}</div>
                                <div class="text-xs text-gray-400 mb-2">Status: {{ $reservation->status }} • Rate: Rp {{ number_format($reservation->daily_rate_idr) }}/malam • Total: Rp {{ number_format($reservation->total_room_charge_idr) }}</div>

                                @if($reservation->status === 'checked_in')
                                <form action="{{ route('hotel.reservations.settle', $reservation) }}" method="POST" class="space-y-2 pt-2 border-t border-gray-700">
                                    @csrf
                                    <input type="hidden" name="payment_amount" value="{{ $reservation->total_room_charge_idr }}">
                                    <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded transition">
                                        Settle Folio (Rp {{ number_format($reservation->total_room_charge_idr) }})
                                    </button>
                                </form>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada reservasi aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Open Folios -->
            <div class="mt-6 bg-gray-700/50 p-4 rounded-lg">
                <h3 class="text-md font-medium text-gray-200 mb-3">Folio Terbuka</h3>
                <div class="space-y-2">
                    @forelse($folios as $folio)
                        <div class="p-3 bg-gray-800 rounded border border-gray-600 flex justify-between">
                            <div>
                                <div class="text-white font-medium">Folio #{{ $folio->id }}</div>
                                <div class="text-xs text-gray-400">Room: Rp {{ number_format($folio->total_room_charges) }} • Add-on: Rp {{ number_format($folio->total_addon_charges) }} • Paid: Rp {{ number_format($folio->total_paid) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-amber-300 text-sm font-bold">Rp {{ number_format($folio->outstanding_balance) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-400 text-sm">Semua folio sudah settled.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
