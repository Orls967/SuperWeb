@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🚶</span> Overbooking Relocation & Walk Compensation
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Hospitality protocol for involuntary overbooked guest re-accommodation with guaranteed compensation.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('hotel.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Hotel Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Execute Guest Walk Workflow</h3>
                    <form action="{{ route('hotel.pms.overbooking.walk') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Confirmed Reservation</label>
                            <select name="reservation_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @forelse($activeReservations as $res)
                                    <option value="{{ $res->id }}">{{ $res->reservation_number }} (Rate: Rp {{ number_format($res->daily_rate_idr) }})</option>
                                @empty
                                    <option disabled>No active reservations available</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Alternate Partner Hotel Name</label>
                            <input type="text" name="alternate_property_name" value="The Ritz-Carlton Bali Resort" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Transport / Taxi Allowance (IDR)</label>
                            <input type="number" name="transport_allowance_idr" value="350000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Goodwill Compensation Credit (IDR)</label>
                            <input type="number" name="goodwill_credit_idr" value="1000000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-semibold rounded-lg transition">
                            Initiate Walk & Compensate Guest
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Guaranteed Walk Policy</h3>
                    <ul class="text-xs text-gray-300 space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">1.</span>
                            <span><strong>Complimentary Equal/Superior Stay:</strong> 1st night paid in full at comparable 5-star partner property.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">2.</span>
                            <span><strong>Transportation:</strong> Chauffeur / premium transfer allowance provided immediately.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">3.</span>
                            <span><strong>Goodwill Credit:</strong> F&amp;B voucher or loyalty points credited to guest profile.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
