@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🧾</span> Split Guest Folio Corporate &amp; Personal Settlement
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Multi-payer billing: Route room charges to Corporate AR and F&amp;B / Spa extras to Guest Personal CC.
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
                    <h3 class="text-lg font-semibold text-white mb-4">Settle Split Folio</h3>
                    <form action="{{ route('hotel.pms.split-folios.settle') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Select Open Folio</label>
                            <select name="folio_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @forelse($openFolios as $f)
                                    <option value="{{ $f->id }}">Folio #{{ substr($f->id, 0, 8) }} (Bal: Rp {{ number_format($f->outstanding_balance) }})</option>
                                @empty
                                    <option disabled>No open folios</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Corporate City Ledger Portion (IDR)</label>
                            <input type="number" name="corporate_room_share_idr" value="3750000" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Guest Personal Addon Portion (IDR)</label>
                            <input type="number" name="guest_personal_addon_idr" value="0" min="0" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg transition">
                            Post Split Settlement
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Open Guest Folios</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Folio ID</th>
                                    <th class="p-3 text-right">Room Charges</th>
                                    <th class="p-3 text-right">Addons</th>
                                    <th class="p-3 text-right">Paid</th>
                                    <th class="p-3 text-right">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($openFolios as $f)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ substr($f->id, 0, 12) }}...</td>
                                        <td class="p-3 text-right font-mono">Rp {{ number_format($f->total_room_charges) }}</td>
                                        <td class="p-3 text-right font-mono text-amber-400">Rp {{ number_format($f->total_addon_charges) }}</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($f->total_paid) }}</td>
                                        <td class="p-3 text-right font-mono font-bold text-white">Rp {{ number_format($f->outstanding_balance) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No open folios awaiting settlement.</td>
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
