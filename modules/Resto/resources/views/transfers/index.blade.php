<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    <span class="p-2 bg-gradient-to-tr from-amber-600 to-teal-600 rounded-xl text-white shadow-md shadow-teal-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    </span>
                    <span>Transfer Stok & Dapur Sentral</span>
                </h2>
                <p class="text-sm text-slate-500 mt-1">Distribusi bahan baku & porsi jadi antar outlet dengan pelacakan in-transit di ledger</p>
            </div>

            <!-- Outlet Filter & Create Button -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('resto.transfers.index') }}" class="flex items-center">
                    <select name="outlet_id" onchange="this.form.submit()" class="text-sm font-semibold rounded-xl border-slate-300 bg-white text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Semua Outlet</option>
                        @foreach($outlets as $ot)
                            <option value="{{ $ot->id }}" {{ $selectedOutletId == $ot->id ? 'selected' : '' }}>
                                {{ $ot->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <button type="button" @click="$dispatch('open-create-transfer')" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-teal-600 hover:from-amber-700 hover:to-teal-700 text-white rounded-xl text-sm font-bold shadow-md shadow-teal-500/20 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    <span>+ Kirim Transfer Stok</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalCreateTransfer: false }" @open-create-transfer.window="modalCreateTransfer = true">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Transfers List Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Daftar Pengiriman Transfer Stok</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Status perpindahan bahan baku antar cabang RM Sari Ranah</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Nomor Transfer</th>
                                <th class="p-4">Dari Outlet</th>
                                <th class="p-4">Ke Outlet</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Total Dikirim</th>
                                <th class="p-4 text-right">Total Diterima</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($transfers as $trf)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-slate-900">
                                        <a href="{{ route('resto.transfers.show', $trf) }}" class="hover:text-teal-600">
                                            {{ $trf->number }}
                                        </a>
                                        <span class="block text-[11px] text-slate-400 font-sans font-normal">{{ $trf->created_at->format('d/m/Y H:i') }} WIB</span>
                                    </td>
                                    <td class="p-4 font-semibold text-slate-800">{{ $trf->fromOutlet?->name }}</td>
                                    <td class="p-4 font-semibold text-slate-800">{{ $trf->toOutlet?->name }}</td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            {{ $trf->status->value === 'received' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $trf->status->value === 'in_transit' ? 'bg-blue-100 text-blue-800 animate-pulse' : '' }}
                                            {{ $trf->status->value === 'discrepancy' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $trf->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}">
                                            {{ $trf->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right font-medium text-slate-700">
                                        {{ number_format((float)$trf->qty_shipped, 2) }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-emerald-700">
                                        {{ number_format((float)$trf->qty_received, 2) }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="{{ route('resto.transfers.show', $trf) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-teal-50 hover:text-teal-600 font-semibold text-xs text-slate-700 transition-all">
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400">
                                        Belum ada pengiriman transfer stok. Klik "+ Kirim Transfer Stok" untuk mengirim bahan ke outlet lain.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($transfers->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $transfers->links() }}
                    </div>
                @endif
            </div>

            <!-- MODAL: KIRIM TRANSFER BARU -->
            <div x-show="modalCreateTransfer" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl border border-slate-100 space-y-4 max-h-[90vh] overflow-y-auto"
                     x-data="{
                         lines: [
                             { ingredient_id: '', qty: 5 }
                         ],
                         addLine() {
                             this.lines.push({ ingredient_id: '', qty: 5 });
                         },
                         removeLine(index) {
                             if (this.lines.length > 1) {
                                 this.lines.splice(index, 1);
                             }
                         }
                     }">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Kirim Transfer Stok Bahan Baku</h3>
                        <button type="button" @click="modalCreateTransfer = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.transfers.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Dari Outlet (Asal)</label>
                                <select name="from_outlet_id" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    @foreach($outlets as $ot)
                                        <option value="{{ $ot->id }}">{{ $ot->name }} ({{ $ot->code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Ke Outlet (Tujuan)</label>
                                <select name="to_outlet_id" required class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    @foreach($outlets as $ot)
                                        <option value="{{ $ot->id }}">{{ $ot->name }} ({{ $ot->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Pengiriman</label>
                            <input type="text" name="note" placeholder="Pengiriman via kurir internal box #12" class="w-full rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 text-sm">
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Item Bahan Ditransfer</label>
                                <button type="button" @click="addLine()" class="text-xs font-bold text-teal-600 hover:text-teal-800">+ Tambah Baris</button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(line, idx) in lines" :key="idx">
                                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center gap-2">
                                        <div class="flex-1">
                                            <select :name="'lines[' + idx + '][ingredient_id]'" x-model="line.ingredient_id" required class="w-full rounded-xl border-slate-300 text-xs">
                                                <option value="">-- Pilih Bahan Baku --</option>
                                                @foreach($ingredients as $ing)
                                                    <option value="{{ $ing->id }}">{{ $ing->name }} (Satuan: {{ $ing->base_unit->value }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-32">
                                            <input type="number" step="0.001" :name="'lines[' + idx + '][qty]'" x-model.number="line.qty" required placeholder="Jumlah" class="w-full rounded-xl border-slate-300 text-xs font-semibold">
                                        </div>
                                        <button type="button" @click="removeLine(idx)" class="text-red-500 hover:text-red-700 font-bold px-2">&times;</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalCreateTransfer = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-teal-600 hover:from-amber-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold transition-all shadow-md">Kirim Transfer (In Transit)</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
