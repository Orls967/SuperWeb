<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('mall.billing.index') }}" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                        Rincian Tagihan #{{ $invoice->invoice_number }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Periode {{ $invoice->period_month }} &bull; Unit {{ $invoice->lease?->unit?->unit_number }} ({{ $invoice->tenant?->brand_name }})</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($invoice->remainingAmount() > 0)
                    <form action="{{ route('mall.invoices.auto-debit', $invoice->id) }}" method="POST" onsubmit="return confirm('Eksekusi pemotongan saldo dompet tenant sekarang?');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-500/20 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span>Auto-Debit Dompet</span>
                        </button>
                    </form>
                @endif
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Cetak Tagihan</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-rose-500">&times;</button>
                </div>
            @endif

            <!-- Invoice Document Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-8">
                <!-- Header Info -->
                <div class="flex flex-col sm:flex-row justify-between items-start gap-6 border-b border-slate-100 pb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-600 text-white font-black text-xs rounded-lg uppercase tracking-wider">
                                {{ $invoice->property?->code ?? 'MALL' }}
                            </span>
                            <h3 class="font-black text-xl text-slate-800">{{ $invoice->property?->name }}</h3>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm">{{ $invoice->property?->address }}, {{ $invoice->property?->city }}</p>
                    </div>

                    <div class="text-right sm:text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $invoice->status->badgeClass() }}">
                            {{ $invoice->status->label() }}
                        </span>
                        <div class="text-sm font-mono font-bold text-slate-700 mt-2">{{ $invoice->invoice_number }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">Jatuh Tempo: <span class="font-bold text-slate-800">{{ $invoice->due_date->format('d F Y') }}</span></div>
                    </div>
                </div>

                <!-- Parties & Lease Info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50/70 rounded-xl p-4 border border-slate-100 text-xs">
                    <div>
                        <span class="font-bold text-slate-400 uppercase tracking-wider block mb-1">Ditagihkan Kepada:</span>
                        <div class="font-black text-sm text-slate-800">{{ $invoice->tenant?->brand_name }}</div>
                        <div class="text-slate-600 font-medium">{{ $invoice->tenant?->company_name }}</div>
                        <div class="text-slate-500 mt-1">PIC: {{ $invoice->tenant?->pic_name }} ({{ $invoice->tenant?->pic_phone }})</div>
                        <div class="text-slate-500">NPWP: {{ $invoice->tenant?->npwp ?? '-' }}</div>
                    </div>

                    <div>
                        <span class="font-bold text-slate-400 uppercase tracking-wider block mb-1">Informasi Unit & Kontrak:</span>
                        <div class="font-bold text-slate-800">Unit: <span class="font-black text-blue-600">{{ $invoice->lease?->unit?->unit_number }}</span> (Lantai {{ $invoice->lease?->unit?->floor }})</div>
                        <div class="text-slate-600">Luas: {{ $invoice->lease?->unit?->area_sqm }} m² &bull; Zona: {{ $invoice->lease?->unit?->zone?->name ?? '-' }}</div>
                        <div class="text-slate-500 mt-1">No. Kontrak: {{ $invoice->lease?->lease_number }}</div>
                        <div class="text-slate-500">Model Sewa: {{ $invoice->lease?->rent_model?->label() }}</div>
                    </div>
                </div>

                <!-- Lines Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 uppercase font-semibold text-[11px] border-b border-slate-200">
                                <th class="py-3 px-3">Item / Uraian Tagihan</th>
                                <th class="py-3 px-3 text-center">Volume</th>
                                <th class="py-3 px-3 text-right">Tarif Satuan</th>
                                <th class="py-3 px-3 text-right">Subtotal</th>
                                <th class="py-3 px-3 text-right">Terbayar</th>
                                <th class="py-3 px-3 text-right">Sisa Tagihan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($invoice->lines as $line)
                                <tr>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-slate-800">{{ $line->description }}</div>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $line->type->label() }}</span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-medium">{{ $line->quantity }}</td>
                                    <td class="py-3 px-3 text-right font-medium">Rp {{ number_format($line->unit_price, 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-800">Rp {{ number_format($line->amount, 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right font-medium text-emerald-600">Rp {{ number_format($line->paid_amount, 0, ',', '.') }}</td>
                                    <td class="py-3 px-3 text-right font-black {{ $line->remainingAmount() > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                        Rp {{ number_format($line->remainingAmount(), 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Summary Box -->
                <div class="flex flex-col sm:flex-row justify-between items-start gap-4 pt-4 border-t border-slate-100">
                    <div class="text-xs text-slate-500 max-w-sm">
                        <p class="font-bold text-slate-700">Ketentuan Pembayaran:</p>
                        <p class="mt-0.5">Pelunasan otomatis mengikuti prioritas: Denda &rarr; Utilitas &rarr; Service Charge &rarr; Sewa Pokok.</p>
                        @if($invoice->payment_reference)
                            <p class="mt-2 font-mono text-[10px] text-slate-400">Ref Transaksi: {{ $invoice->payment_reference }}</p>
                        @endif
                    </div>

                    <div class="w-full sm:w-72 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal Tagihan:</span>
                            <span class="font-bold">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if($invoice->penalty_amount > 0)
                            <div class="flex justify-between text-rose-600">
                                <span>Denda Keterlambatan:</span>
                                <span class="font-bold">+ Rp {{ number_format($invoice->penalty_amount, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                            <span>Grand Total:</span>
                            <span>Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-600 font-bold">
                            <span>Sudah Dibayar:</span>
                            <span>- Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-black text-rose-600 pt-2 border-t border-slate-200">
                            <span>Sisa Pembayaran:</span>
                            <span>Rp {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
