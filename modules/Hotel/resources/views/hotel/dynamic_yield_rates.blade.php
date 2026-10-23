@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>📈</span> Dynamic Yield Management & Rate Parity Engine
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        115% cost floor protection, occupancy yield multipliers, and OTA commission markups.
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
                    <h3 class="text-lg font-semibold text-white mb-4">Calculate Rate Yield</h3>
                    <form action="{{ route('hotel.pms.yield-rates.calculate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Property</label>
                            <select name="property_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}">{{ $prop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Room Category</label>
                            <select name="room_type" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="DELUXE">Deluxe King Room</option>
                                <option value="SUITE">Executive Suite</option>
                                <option value="PRESIDENTIAL">Presidential Penthouse</option>
                                <option value="VILLA">Private Pool Villa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Base Unit Operating Cost (IDR)</label>
                            <input type="number" name="base_cost_idr" value="800000" min="100000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Projected Occupancy (%)</label>
                            <input type="number" step="0.5" name="occupancy_pct" value="88.0" min="0" max="100" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Distribution Channel</label>
                            <select name="channel" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="DIRECT">Brand Website Direct (0% comm)</option>
                                <option value="OTA_BOOKING">Booking.com (15% markup)</option>
                                <option value="OTA_AGODA">Agoda Merchant (15% markup)</option>
                                <option value="CORPORATE">Corporate Negotiated (-10% disc)</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Compute Yield & Floor Boundary
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">Revenue Management Principles</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700">
                            <span class="font-bold text-white text-sm block mb-1">Mandatory Cost Floor (115%)</span>
                            <p class="text-xs text-gray-400">Rates strictly cannot fall below 1.15x base housekeeping and operational cost to prevent capital depreciation.</p>
                        </div>
                        <div class="p-3 bg-gray-800/80 rounded border border-gray-700">
                            <span class="font-bold text-white text-sm block mb-1">High Compression Surge (&ge; 85%)</span>
                            <p class="text-xs text-gray-400">Triggers 1.40x multiplier to capitalize on local demand compression events.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
