<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.transfers.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Transfer #{{ $transfer->number }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $transfer->fromOutlet?->name }} &rarr; {{ $transfer->toOutlet?->name }}</p>
                </div>
            </div>

            <span class="px-3 py-1 rounded-full text-xs font-bold
                {{ $transfer->status->value === 'received' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $transfer->status->value === 'in_transit' ? 'bg-blue-100 text-blue-800 animate-pulse' : '' }}
                {{ $transfer->status->value === 'discrepancy' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $transfer->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}">
                {{ $transfer->status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalReceive: false }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Info Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Status Pengiriman</span>
                    <h3 class="text-lg font-bold text-slate-800 mt-1">{{ $transfer->status->label() }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Dikirim: {{ $transfer->shipped_at?->format('d/m/Y H:i') }} WIB</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pengirim & Penerima</span>
                    <h3 class="text-sm font-bold text-slate-800 mt-1">Pengirim: {{ $transfer->shipper?->name }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Penerima: {{ $transfer->receiver?->name ?: 'Menunggu penerimaan di outlet tujuan' }}</p>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Catatan Pengiriman</span>
                    <p class="text-sm text-slate-700 mt-1 italic">{{ $transfer->variance_note ?: 'Tidak ada catatan khusus.' }}</p>
                </div>
            </div>

            <!-- Lines Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Daftar Bahan Baku Ditransfer</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Perbandingan kuantitas dikirim vs diterima</p>
                    </div>

                    @if($transfer->status->value === 'in_transit')
                        <button type="button" @click="modalReceive = true" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Terima Transfer di Outlet</span>
                        </button>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Bahan Baku</th>
                                <th class="p-4 text-right">Qty Dikirim</th>
                                <th class="p-4 text-right">Qty Diterima</th>
                                <th class="p-4 text-right">Biaya per Satuan</th>
                                <th class="p-4 text-right">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($transfer->lines as $line)
                                <tr>
                                    <td class="p-4 font-bold text-slate-800">{{ $line['name'] }}</td>
                                    <td class="p-4 text-right font-medium text-slate-700">{{ number_format((float)$line['qty_shipped'], 2) }}</td>
                                    <td class="p-4 text-right font-bold text-emerald-700">{{ number_format((float)$line['qty_received'], 2) }}</td>
                                    <td class="p-4 text-right font-mono text-slate-600">Rp {{ number_format((float)$line['unit_cost'], 2, ',', '.') }}</td>
                                    <td class="p-4 text-right font-black text-slate-900">Rp {{ number_format($line['line_cost'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL: TERIMA TRANSFER -->
            <div x-show="modalReceive" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-100 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Penerimaan Transfer #{{ $transfer->number }}</h3>
                        <button type="button" @click="modalReceive = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.transfers.receive', $transfer) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Penerimaan / Alasan Selisih</label>
                            <input type="text" name="variance_note" placeholder="Diterima utuh tanpa ada kebocoran" class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                        </div>

                        <div class="space-y-3">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Hitungan Fisik Diterima</label>
                            @foreach($transfer->lines as $line)
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between gap-3">
                                    <div>
                                        <span class="font-bold text-xs text-slate-800">{{ $line['name'] }}</span>
                                        <span class="block text-[11px] text-slate-400">Dikirim: {{ (float)$line['qty_shipped'] }}</span>
                                    </div>
                                    <div class="w-32">
                                        <input type="number" step="0.001" name="received_qtys[{{ $line['ingredient_id'] }}]" value="{{ (float)$line['qty_shipped'] }}" required class="w-full rounded-lg border-slate-300 text-xs font-bold">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalReceive = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">Konfirmasi Penerimaan Stok</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
