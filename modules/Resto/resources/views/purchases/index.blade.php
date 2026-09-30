<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    <span class="p-2 bg-gradient-to-tr from-amber-600 to-indigo-600 rounded-xl text-white shadow-md shadow-indigo-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </span>
                    <span>Rantai Pasok & Purchase Orders</span>
                </h2>
                <p class="text-sm text-slate-500 mt-1">Pengadaan bahan baku, penerimaan barang, moving average cost & utang dagang supplier</p>
            </div>

            <!-- Outlet Filter & Create Button -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('resto.purchases.index') }}" class="flex items-center">
                    <select name="outlet_id" onchange="this.form.submit()" class="text-sm font-semibold rounded-xl border-slate-300 bg-white text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua Outlet</option>
                        @foreach($outlets as $ot)
                            <option value="{{ $ot->id }}" {{ $selectedOutletId == $ot->id ? 'selected' : '' }}>
                                {{ $ot->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <button type="button" @click="$dispatch('open-create-po')" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-indigo-600 hover:from-amber-700 hover:to-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    <span>+ Buat PO Baru</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ modalCreatePo: false }" @open-create-po.window="modalCreatePo = true">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Aging Payables Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Utang Dagang</span>
                    <h3 class="text-2xl font-black text-slate-800 mt-1">Rp {{ number_format($aging['total'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Seluruh supplier aktif</p>
                </div>

                <div class="p-5 bg-emerald-50/50 rounded-2xl border border-emerald-200 shadow-sm">
                    <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Lancar (0 - 30 Hari)</span>
                    <h3 class="text-2xl font-black text-emerald-800 mt-1">Rp {{ number_format($aging['bracket_0_30'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-emerald-600 mt-1">Dalam masa jatuh tempo normal</p>
                </div>

                <div class="p-5 bg-amber-50/50 rounded-2xl border border-amber-200 shadow-sm">
                    <span class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Jatuh Tempo (31 - 60 Hari)</span>
                    <h3 class="text-2xl font-black text-amber-800 mt-1">Rp {{ number_format($aging['bracket_31_60'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-amber-600 mt-1">Perlu segera dijadwalkan bayar</p>
                </div>

                <div class="p-5 bg-red-50/50 rounded-2xl border border-red-200 shadow-sm">
                    <span class="text-xs font-semibold text-red-700 uppercase tracking-wider">Overdue (> 60 Hari)</span>
                    <h3 class="text-2xl font-black text-red-800 mt-1">Rp {{ number_format($aging['bracket_60_plus'], 0, ',', '.') }}</h3>
                    <p class="text-xs text-red-600 mt-1">Prioritas pelunasan mendesak</p>
                </div>
            </div>

            <!-- PO List Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Daftar Purchase Order</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Riwayat pesanan bahan baku ke supplier</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Nomor PO</th>
                                <th class="p-4">Outlet</th>
                                <th class="p-4">Supplier</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Grand Total</th>
                                <th class="p-4 text-right">Sisa Utang</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($pos as $po)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-slate-900">
                                        <a href="{{ route('resto.purchases.show', $po) }}" class="hover:text-indigo-600">
                                            {{ $po->number }}
                                        </a>
                                        <span class="block text-[11px] text-slate-400 font-sans font-normal">{{ $po->created_at->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="p-4 font-medium text-slate-700">{{ $po->outlet?->name }}</td>
                                    <td class="p-4">
                                        <span class="font-semibold text-slate-800">{{ $po->supplier?->name }}</span>
                                        <span class="block text-xs text-slate-400">Tempo {{ $po->supplier?->terms_days }} hari</span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            {{ $po->status->value === 'received' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $po->status->value === 'partially_received' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $po->status->value === 'sent' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $po->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}
                                            {{ $po->status->value === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ $po->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900">
                                        Rp {{ number_format($po->grand_total, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-bold {{ $po->remainingPayable() > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                        Rp {{ number_format($po->remainingPayable(), 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="{{ route('resto.purchases.show', $po) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600 font-semibold text-xs text-slate-700 transition-all">
                                            Rincian & Terima &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400">
                                        Belum ada Purchase Order. Klik "+ Buat PO Baru" untuk membuat pesanan ke supplier.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($pos->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $pos->links() }}
                    </div>
                @endif
            </div>

            <!-- MODAL: BUAT PO BARU -->
            <div x-show="modalCreatePo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl border border-slate-100 space-y-4 max-h-[90vh] overflow-y-auto"
                     x-data="{
                         lines: [
                             { ingredient_id: '', qty: 10, unit: 'kg', unit_price: 50000 }
                         ],
                         addLine() {
                             this.lines.push({ ingredient_id: '', qty: 10, unit: 'kg', unit_price: 50000 });
                         },
                         removeLine(index) {
                             if (this.lines.length > 1) {
                                 this.lines.splice(index, 1);
                             }
                         }
                     }">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="font-bold text-lg text-slate-800">Buat Purchase Order Baru</h3>
                        <button type="button" @click="modalCreatePo = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                    </div>

                    <form method="POST" action="{{ route('resto.purchases.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Outlet Penerima</label>
                                <select name="outlet_id" required class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach($outlets as $ot)
                                        <option value="{{ $ot->id }}">{{ $ot->name }} ({{ $ot->code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Supplier</label>
                                <select name="supplier_id" required class="w-full rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }} (Tempo: {{ $sup->terms_days }} hari)</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Item Bahan Baku</label>
                                <button type="button" @click="addLine()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">+ Tambah Baris</button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(line, idx) in lines" :key="idx">
                                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-wrap items-center gap-2">
                                        <div class="flex-1 min-w-[200px]">
                                            <select :name="'lines[' + idx + '][ingredient_id]'" x-model="line.ingredient_id" required class="w-full rounded-xl border-slate-300 text-xs">
                                                <option value="">-- Pilih Bahan --</option>
                                                @foreach($ingredients as $ing)
                                                    <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->sku }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-24">
                                            <input type="number" step="0.001" :name="'lines[' + idx + '][qty]'" x-model.number="line.qty" required placeholder="Qty" class="w-full rounded-xl border-slate-300 text-xs">
                                        </div>
                                        <div class="w-20">
                                            <input type="text" :name="'lines[' + idx + '][unit]'" x-model="line.unit" placeholder="kg" class="w-full rounded-xl border-slate-300 text-xs">
                                        </div>
                                        <div class="w-32">
                                            <input type="number" :name="'lines[' + idx + '][unit_price]'" x-model.number="line.unit_price" required placeholder="Harga" class="w-full rounded-xl border-slate-300 text-xs">
                                        </div>
                                        <button type="button" @click="removeLine(idx)" class="text-red-500 hover:text-red-700 font-bold px-2">&times;</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="modalCreatePo = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-indigo-600 hover:from-amber-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md">Kirim PO ke Supplier</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
