<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.purchases.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Purchase Order #{{ $po->number }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">Supplier: {{ $po->supplier?->name }} • Outlet: {{ $po->outlet?->name }}</p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-bold
                {{ $po->status->value === 'received' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $po->status->value === 'partially_received' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $po->status->value === 'sent' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $po->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}">
                {{ $po->status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalReceive: false, modalPay: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Nilai PO</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1">Rp {{ number_format($po->grand_total, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-400 mt-1">Termasuk PPN 11%</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sudah Dibayar</span>
                    <h3 class="text-2xl font-black text-emerald-600 mt-1">Rp {{ number_format($po->paid_amount, 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-400 mt-1">Pelunasan utang ke supplier</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sisa Utang Dagang (AP)</span>
                    <h3 class="text-2xl font-black {{ $po->remainingPayable() > 0 ? 'text-amber-600' : 'text-slate-400' }} mt-1">
                        Rp {{ number_format($po->remainingPayable(), 0, ',', '.') }}
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Jatuh tempo: {{ $po->created_at->addDays($po->supplier->terms_days)->format('d/m/Y') }}</p>
                </div>
            </div>

            <!-- Ordered Lines Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Daftar Bahan Baku Dipesan</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Status kuantitas pesanan vs kuantitas barang yang diterima</p>
                    </div>

                    @if($po->status->value !== 'received' && $po->status->value !== 'cancelled')
                        <button type="button" @click="modalReceive = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Terima Barang (Goods Receipt)</span>
                        </button>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Bahan Baku</th>
                                <th class="p-4 text-right">Qty Pesan</th>
                                <th class="p-4 text-right">Qty Diterima</th>
                                <th class="p-4 text-right">Harga Satuan</th>
                                <th class="p-4 text-right">Total</th>
                                <th class="p-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($po->lines as $line)
                                <tr>
                                    <td class="p-4">
                                        <span class="font-bold text-slate-800">{{ $line->ingredient?->name }}</span>
                                        <span class="block text-xs text-slate-400 font-mono">{{ $line->ingredient?->sku }}</span>
                                    </td>
                                    <td class="p-4 text-right font-medium text-slate-700">
                                        {{ number_format((float)$line->qty_ordered, 2) }} {{ $line->unit }}
                                        <span class="block text-[11px] text-slate-400">({{ number_format((float)$line->qty_base_unit, 0) }} {{ $line->ingredient?->base_unit->value }})</span>
                                    </td>
                                    <td class="p-4 text-right font-bold text-emerald-700">
                                        {{ number_format((float)$line->qty_received, 0) }} {{ $line->ingredient?->base_unit->value }}
                                    </td>
                                    <td class="p-4 text-right font-mono text-slate-600">
                                        Rp {{ number_format($line->unit_price, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900">
                                        Rp {{ number_format($line->line_total, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        @if($line->isFullyReceived())
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Lengkap</span>
                                        @elseif((float)$line->qty_received > 0)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">Parsial</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">Menunggu</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Goods Receipts History -->
            @if($po->receipts->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="font-bold text-base text-slate-800">Riwayat Penerimaan Fisik (Goods Receipts)</h3>

                    <div class="space-y-3">
                        @foreach($po->receipts as $rc)
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-sm text-slate-800">Receipt #{{ $rc->id }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $rc->quality->value === 'good' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $rc->quality->label() }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">Diterima oleh: <strong>{{ $rc->receiver?->name }}</strong> • {{ $rc->received_at->format('d/m/Y H:i') }} WIB</p>
                                    @if($rc->note)
                                        <p class="text-xs text-slate-600 italic mt-0.5">"{{ $rc->note }}"</p>
                                    @endif
                                </div>

                                <div class="text-right">
                                    <span class="text-xs text-slate-500">Nilai Persediaan Bertambah:</span>
                                    <span class="block font-black text-sm text-slate-900">
                                        Rp {{ number_format($rc->lines->sum('total_cost'), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Pay Supplier Action Box -->
            @if($po->remainingPayable() > 0)
                <div class="p-5 bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl shadow-md flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-base">Pembayaran Utang Dagang (AP Supplier)</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Sisa tagihan yang belum dibayar: Rp {{ number_format($po->remainingPayable(), 0, ',', '.') }}</p>
                    </div>

                    <button type="button" @click="modalPay = true" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                        Bayar Supplier Sekarang
                    </button>
                </div>
            @endif

            <!-- MODAL: TERIMA BARANG -->
            <div x-show="modalReceive" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-100 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Catat Penerimaan Barang PO #{{ $po->number }}</h3>
                        <button type="button" @click="modalReceive = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.purchases.receive', $po) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Kualitas Fisik Barang</label>
                            <select name="quality" required class="w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                <option value="good">Kualitas Baik Sesuai Standar</option>
                                <option value="partial_reject">Sebagian Cacat / Reject</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Penerima</label>
                            <input type="text" name="note" placeholder="Diterima utuh dan segar" class="w-full rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                        </div>

                        <div class="space-y-3">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Kuantitas Diterima (Satuan Dasar)</label>
                            @foreach($po->lines as $idx => $line)
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="font-bold text-slate-800">{{ $line->ingredient?->name }}</span>
                                        <span class="text-slate-500">Pesan: {{ (float)$line->qty_base_unit }} {{ $line->ingredient?->base_unit->value }}</span>
                                    </div>
                                    <input type="hidden" name="lines[{{ $idx }}][po_line_id]" value="{{ $line->id }}">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] text-slate-400">Qty Diterima ({{ $line->ingredient?->base_unit->value }})</label>
                                            <input type="number" step="0.001" name="lines[{{ $idx }}][qty_received_base_unit]" value="{{ max(0, (float)$line->qty_base_unit - (float)$line->qty_received) }}" class="w-full rounded-lg border-slate-300 text-xs font-semibold">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-slate-400">Biaya per {{ $line->ingredient?->base_unit->value }}</label>
                                            <input type="number" step="0.000001" name="lines[{{ $idx }}][unit_cost]" placeholder="Biaya satuan" class="w-full rounded-lg border-slate-300 text-xs">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalReceive = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Konfirmasi Masuk Stok</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: BAYAR SUPPLIER -->
            <div x-show="modalPay" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Bayar Utang Supplier</h3>
                        <button type="button" @click="modalPay = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.purchases.pay', $po) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Nominal Pembayaran (Rp)</label>
                            <input type="number" name="amount" required min="1" max="{{ $po->remainingPayable() }}" value="{{ $po->remainingPayable() }}" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Pembayaran / No Referensi Bank</label>
                            <input type="text" name="note" placeholder="Transfer BCA ref #998877" class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalPay = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Posting Pelunasan Ledger</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
