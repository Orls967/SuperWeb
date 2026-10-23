@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>✈️</span> Multi-Vendor Destination Holiday Packages
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Fase 92: Cross-ecosystem escrow settlement across Resorts, Airlines, and Tour Operators.
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Settle Multi-Vendor Package</h3>
                    <form action="{{ route('hotel.pms.destination-packages.settle') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Package Code</label>
                            <input type="text" name="package_code" value="PKG-BALI-ESCAPE-01" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm uppercase font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Package Title</label>
                            <input type="text" name="title" value="Bali 4D3N Luxury Villa & Yacht Experience" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Hotel / Resort Share (IDR)</label>
                            <input type="number" name="hotel_amount_idr" value="12000000" min="100000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Airline Partner Share (IDR)</label>
                            <input type="number" name="flight_amount_idr" value="5500000" min="100000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Tour & Yacht Operator Share (IDR)</label>
                            <input type="number" name="activity_amount_idr" value="4500000" min="100000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Split Escrow Across Vendors
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Destination Package Ledger</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Package Code</th>
                                    <th class="p-3">Title</th>
                                    <th class="p-3 text-right">Total Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($packages as $pkg)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $pkg->package_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $pkg->title }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($pkg->total_price_idr) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-4 text-center text-gray-500">No destination packages registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
