@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🎟️</span> Festival Gapless Sequential Ticketing
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Cryptographic hash chaining, tamper-proof anti-scalping digital pass issuance.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('venue.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        Back to Venue Hub
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
                    <h3 class="text-lg font-semibold text-white mb-4">Issue Festival Ticket</h3>
                    <form action="{{ route('venue.ticketing.issue') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Target Event</label>
                            <select name="event_id" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                @foreach($events as $ev)
                                    <option value="{{ $ev->id }}">{{ $ev->title }} ({{ $ev->event_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Ticket Tier</label>
                            <select name="ticket_tier" class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                                <option value="GENERAL_ADMISSION">General Admission</option>
                                <option value="VIP_SUNSET">VIP Sunset Pass</option>
                                <option value="BACKSTAGE_ALL_ACCESS">Backstage All-Access</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Price (IDR)</label>
                            <input type="number" name="price_paid_idr" value="750000" min="50000" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg transition">
                            Mint Hash-Chained Ticket
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Issued Tickets Ledger</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Ticket #</th>
                                    <th class="p-3">Event</th>
                                    <th class="p-3">Hash Digest</th>
                                    <th class="p-3 text-right">Price</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($tickets as $t)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $t->ticket_number }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $t->event ? $t->event->title : 'Event #'.$t->event_id }}</td>
                                        <td class="p-3 font-mono text-[11px] text-gray-400">{{ substr($t->ticket_hash, 0, 16) }}...</td>
                                        <td class="p-3 text-right font-mono text-emerald-400">Rp {{ number_format($t->price_paid_idr) }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">
                                                {{ $t->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-4 text-center text-gray-500">No tickets minted yet.</td>
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
