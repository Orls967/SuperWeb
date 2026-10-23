@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🔐</span> Smart Lock Cryptographic Access PIN Generator
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Zero-trust IoT access control: One-time hashed door relays and passport verifications.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('proptech.flex.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Flex Overview
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
                    <h3 class="text-lg font-semibold text-white mb-4">Generate Door Access Token</h3>
                    <form action="{{ route('proptech.intelligence.smart-lock.generate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Active Booking</label>
                            <select name="flex_booking_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($bookings as $b)
                                    <option value="{{ $b->id }}">{{ $b->booking_code }} (User #{{ $b->user_id }}) &ndash; {{ $b->status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Cryptographic Passport Hash</label>
                            <input type="text" name="passport_hash" value="f7d8c6b2a4e10987654321fedcba0987654321abcdef0123456789abcdef0123" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm font-mono text-xs">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                            Issue 6-Digit IoT Relay PIN
                        </button>
                    </form>
                </div>

                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700 space-y-4">
                    <h3 class="text-lg font-semibold text-white">IoT Access Protocol Specifications</h3>
                    <ul class="text-xs text-gray-300 space-y-3">
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Dynamic Key Rolling:</strong> PIN expires strictly when the flex-space booking schedule ends.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Zero Cloud Latency:</strong> Local relay hardware validates cryptographic HMAC tokens on edge microcontrollers.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-emerald-400 font-bold">✓</span>
                            <span><strong>Audit Trail:</strong> Access events trigger real-time occupancy updates in the Digital Twin BMS ledger.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
