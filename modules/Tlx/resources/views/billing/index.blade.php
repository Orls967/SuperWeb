@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-4 sm:p-8 bg-gray-800 shadow sm:rounded-lg">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-white">Telecom CDR Rating & Connectivity Invoices</h2>
                    <p class="mt-1 text-sm text-gray-400">Penagihan Pemakaian CDR (Voice/SMS/Data) & Settlement Kliring Roaming</p>
                </div>
                <a href="{{ route('tlx.billing.roaming') }}" class="text-sm text-indigo-400 hover:underline">Kliring Roaming Antar-Operator &rarr;</a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/50 border border-emerald-500 rounded text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Invoices List -->
                <div class="lg:col-span-2 bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Invoice Konektivitas IoT & CDR Terbit ({{ $invoices->count() }})</h3>
                    <div class="space-y-3">
                        @forelse($invoices as $inv)
                            <div class="p-4 bg-gray-800 rounded border border-gray-600 flex justify-between items-center">
                                <div>
                                    <div class="text-white font-mono font-medium">{{ $inv->invoice_number }}</div>
                                    <div class="text-xs text-gray-400 mt-1">Tenant: {{ $inv->device_id ?? 'Enterprise' }} • Periode: {{ $inv->billing_period }}</div>
                                    <div class="text-xs text-gray-300 mt-1">Pemakaian: {{ $inv->data_usage_mb }} MB • Status: {{ $inv->payment_status }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-emerald-400 font-semibold">Rp {{ number_format($inv->amount_due_minor, 0, ',', '.') }}</div>
                                    <div class="text-xs text-gray-400 mt-1">Jatuh Tempo: {{ $inv->due_date }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-400 text-sm">Belum ada invoice konektivitas.</div>
                        @endforelse
                    </div>
                </div>

                <!-- CDR Rating Form -->
                <div class="bg-gray-700/50 p-6 rounded-lg">
                    <h3 class="text-lg font-medium text-white mb-4">Proses Rating Batch CDR</h3>
                    <form action="{{ route('tlx.billing.cdr.rate') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-300">Client Tenant ID</label>
                            <input type="text" name="client_tenant_id" required value="TNT-TELCO-01" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Bulan Periode</label>
                            <input type="text" name="billing_month" required value="2026-10" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Total Durasi Suara (Detik)</label>
                            <input type="number" name="voice_seconds" required min="0" value="12000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Total SMS</label>
                            <input type="number" name="sms_count" required min="0" value="450" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-300">Konsumsi Data (MB)</label>
                            <input type="number" name="data_megabytes" required min="0" value="85000" class="mt-1 w-full bg-gray-800 border border-gray-600 rounded px-3 py-1.5 text-sm text-white">
                        </div>
                        <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-semibold">
                            Rate & Terbitkan Tagihan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
