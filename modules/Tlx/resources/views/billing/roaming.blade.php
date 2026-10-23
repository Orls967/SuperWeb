@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Kliring & Net Settlement Roaming Antar-Operator</h2>
                    <p class="mt-1 text-sm text-gray-400">Penyelesaian Timbal-Balik Lalu Lintas Inbound & Outbound Roaming</p>
                </div>
                <a href="{{ route('tlx.billing.index') }}" class="text-sm text-indigo-400 hover:underline">&larr; Kembali ke Billing</a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Settlement Records -->
                <div class="lg:col-span-2 bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Riwayat Kliring Roaming ({{ $settlements->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($settlements as $s)
                            <div class="p-4 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-mono font-medium">{{ $s->settlement_batch_code }}</div>
                                    <div class="text-xs text-gray-400 mt-1">Operator Mitra: {{ $s->partner_operator_code }} • Bulan: {{ $s->period_month }}</div>
                                    <div class="text-xs text-gray-300 mt-1">Inbound: {{ $s->inbound_minutes }} mnt | Outbound: {{ $s->outbound_minutes }} mnt</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold {{ $s->net_settlement_minor >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                        Net: Rp {{ number_format($s->net_settlement_minor, 0, ',', '.') }}
                                    </div>
                                    <div class="text-xs px-2 py-0.5 rounded mt-1 inline-block bg-indigo-900 text-indigo-200">{{ $s->status }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada penyelesaian kliring roaming.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Settle Roaming Form -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Eksekusi Kliring Roaming</h3>
                    <form action="{{ route('tlx.billing.roaming.settle') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300">Kode Operator Mitra</label>
                            <input type="text" name="partner_operator_code" required value="OP-SINGTEL-SG" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Bulan Periode</label>
                            <input type="text" name="period_month" required value="2026-10" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Menit Roaming Inbound</label>
                            <input type="number" name="inbound_minutes" required min="0" value="45000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Menit Roaming Outbound</label>
                            <input type="number" name="outbound_minutes" required min="0" value="38000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Tarif Inbound (Minor IDR/mnt)</label>
                            <input type="number" name="inbound_rate_minor" required min="0" value="350" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Tarif Outbound (Minor IDR/mnt)</label>
                            <input type="number" name="outbound_rate_minor" required min="0" value="300" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold">
                            Selesaikan Kliring
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
