<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Rincian Tagihan: ') }} {{ $invoice->invoice_number }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Properti: {{ $invoice->property_id }} | Periode: {{ $invoice->billing_period }} | Status: {{ strtoupper($invoice->status) }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('egy.bills.index') }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-md text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Invoice Overview Summary Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="border-l-4 border-indigo-500 pl-4">
                        <div class="text-xs text-gray-500 uppercase">Total Kewajiban Tagihan</div>
                        <div class="text-2xl font-bold font-mono text-gray-900 mt-1">
                            Rp {{ number_format($invoice->total_amount_idr) }}
                        </div>
                        <div class="text-2xs text-gray-400 mt-1">PPN Termasuk: Rp {{ number_format($invoice->tax_amount_idr ?? 0) }}</div>
                    </div>
                    <div class="border-l-4 border-emerald-500 pl-4">
                        <div class="text-xs text-gray-500 uppercase">Total Terbayar</div>
                        <div class="text-2xl font-bold font-mono text-emerald-700 mt-1">
                            Rp {{ number_format($invoice->paid_amount_idr ?? 0) }}
                        </div>
                        <div class="text-2xs text-gray-400 mt-1">Sisa: Rp {{ number_format(max(0, $invoice->total_amount_idr - ($invoice->paid_amount_idr ?? 0))) }}</div>
                    </div>
                    <div class="border-l-4 border-amber-500 pl-4">
                        <div class="text-xs text-gray-500 uppercase">Konsumsi & Peak Demand</div>
                        <div class="text-xl font-bold font-mono text-gray-800 mt-1">
                            {{ number_format(($invoice->total_kwh_consumption ?? 0), 2) }} kWh
                        </div>
                        <div class="text-2xs text-gray-500 mt-1">Puncak: {{ number_format($invoice->peak_demand_kw ?? 0, 2) }} kW</div>
                    </div>
                    <div class="border-l-4 border-purple-500 pl-4">
                        <div class="text-xs text-gray-500 uppercase">Jurnal Buku Besar</div>
                        <div class="text-sm font-mono text-gray-800 mt-1 truncate">
                            {{ $invoice->journal_entry_id ?? 'Belum Posting' }}
                        </div>
                        <div class="text-2xs text-gray-400 mt-1">Jatuh Tempo: {{ $invoice->due_date }}</div>
                    </div>
                </div>
            </div>

            <!-- Line Items Breakdown Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Rincian Komponen Tagihan (Line Items)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Komponen / Tipe Biaya</th>
                                <th class="px-4 py-3 text-left">Deskripsi</th>
                                <th class="px-4 py-3 text-right">Kuantitas</th>
                                <th class="px-4 py-3 text-right">Tarif Satuan (IDR)</th>
                                <th class="px-4 py-3 text-right">Subtotal (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($invoice->lines as $line)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono {{ str_contains($line->line_type, 'credit') ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ strtoupper($line->line_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 text-xs">
                                        {{ $line->description }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        {{ number_format($line->quantity, 2) }} {{ $line->unit ?? 'kWh' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-gray-600">
                                        Rp {{ number_format($line->unit_rate_idr) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold {{ $line->total_amount_idr < 0 ? 'text-emerald-700' : 'text-gray-900' }}">
                                        Rp {{ number_format($line->total_amount_idr) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-400 italic">
                                        Tidak ada baris komponen rincian.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right text-gray-700 uppercase text-xs">Total Akhir</td>
                                <td class="px-4 py-3 text-right font-mono text-base text-indigo-700">
                                    Rp {{ number_format($invoice->total_amount_idr) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Action Buttons: Payment & Reversal / Adjustment -->
            @if($invoice->status !== 'paid' && $invoice->status !== 'voided')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Form Pembayaran Kas/Bank -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                        <h4 class="text-sm font-bold text-gray-900 mb-3">Penerimaan Pembayaran Tagihan</h4>
                        <form action="{{ route('egy.bills.pay', $invoice->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Jumlah Pembayaran (IDR)</label>
                                <input type="number" name="amount_idr" value="{{ max(0, $invoice->total_amount_idr - ($invoice->paid_amount_idr ?? 0)) }}" min="1" required
                                       class="mt-1 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Metode Bayar / Referensi Transaksi</label>
                                <input type="text" name="payment_reference" placeholder="TRX-BANK-12345" required
                                       class="mt-1 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                                Catat Pembayaran Lunas
                            </button>
                        </form>
                    </div>

                    <!-- Form Koreksi / Penyesuaian Append-Only -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                        <h4 class="text-sm font-bold text-gray-900 mb-3">Koreksi / Penyesuaian Append-Only (U-4)</h4>
                        <form action="{{ route('egy.bills.adjust', $invoice->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Nilai Penyesuaian (IDR, positif atau negatif)</label>
                                <input type="number" name="adjustment_amount_idr" placeholder="-50000" step="1" required
                                       class="mt-1 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Alasan Penyesuaian (Audit Log)</label>
                                <input type="text" name="adjustment_reason" placeholder="Koreksi kalibrasi meter AMI interval #44" required
                                       class="mt-1 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                                Terbitkan Baris Penyesuaian & Posting
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
