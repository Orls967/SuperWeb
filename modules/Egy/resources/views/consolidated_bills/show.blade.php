<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Faktur Multi-Utilitas Terpadu: {{ $invoice->invoice_number }}
                </h2>
                <p class="text-sm text-gray-500">Rincian pemakaian listrik, air, pendingin BTU termal, dan alokasi biaya properti</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('egy.bills.consolidated.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">
                    Kembali ke Daftar Tagihan
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-8 rounded-xl border border-gray-100 shadow-sm space-y-6">
                <!-- Header Faktur -->
                <div class="flex justify-between items-start border-b border-gray-100 pb-6">
                    <div>
                        <span class="text-xs uppercase tracking-wider font-bold text-indigo-600">SuperWeb Utility Services</span>
                        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $invoice->invoice_number }}</h1>
                        <p class="text-xs text-gray-500 mt-1">Periode: {{ $invoice->billing_period }} ({{ $invoice->period_start }} s/d {{ $invoice->period_end }})</p>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 uppercase">
                            {{ $invoice->status }}
                        </span>
                        <p class="text-xs text-gray-400 mt-2">Party ID: #{{ $invoice->customer_party_id }}</p>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">Rincian Komponen Tagihan</h3>
                    <table class="w-full text-left text-sm" data-table="invoice-lines-table">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-400">
                            <tr>
                                <th class="py-3 px-4">Komponen Layanan</th>
                                <th class="py-3 px-4">Konsumsi Satuan</th>
                                <th class="py-3 px-4">Tarif Satuan (Rp)</th>
                                <th class="py-3 px-4 text-right">Subtotal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($invoice->lines as $line)
                                <tr>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-900">{{ $line->description }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $line->line_type }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono">{{ number_format($line->wh_consumed / 1000, 2) }}</td>
                                    <td class="py-3 px-4 font-mono">Rp {{ number_format($line->rate_idr_per_kwh) }}</td>
                                    <td class="py-3 px-4 font-bold text-gray-900 text-right">Rp {{ number_format($line->line_total_idr) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-400">Tidak ada baris komponen</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Total Amount Summary -->
                <div class="border-t border-gray-100 pt-6 flex justify-between items-center">
                    <div>
                        <span class="text-xs text-gray-400">Metode Pembayaran</span>
                        <p class="text-sm font-medium text-gray-800">Auto-Debit Banking Ledger</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs uppercase text-gray-400 font-bold">Total Pembayaran</span>
                        <p class="text-3xl font-bold text-indigo-600">Rp {{ number_format($invoice->total_amount_idr) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
