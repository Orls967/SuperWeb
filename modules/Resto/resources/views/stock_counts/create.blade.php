<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.stock-counts.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-slate-900 transition-all">
                    &larr;
                </a>
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Formulir Penghitungan Fisik (Stock Opname)
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">Outlet: {{ $outlet->name }} • Tanggal: {{ date('d/m/Y') }}</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="POST" action="{{ route('resto.stock-counts.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Outlet Dipilih</label>
                            <input type="text" readonly value="{{ $outlet->name }} ({{ $outlet->code }})" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm font-bold text-slate-700">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Catatan Opname</label>
                            <input type="text" name="notes" placeholder="Opname bulanan akhir shift" class="w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500 text-sm">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-base text-slate-800">Daftar Bahan Baku & Stok Sistem</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Masukkan kuantitas fisik riil hasil hitungan di gudang/dapur</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="p-4">Bahan Baku</th>
                                    <th class="p-4">Kategori</th>
                                    <th class="p-4 text-right">Stok Sistem</th>
                                    <th class="p-4 text-right">Satuan Dasar</th>
                                    <th class="p-4 text-right w-44">Hitungan Fisik Riil</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($ingredients as $ing)
                                    <tr>
                                        <td class="p-4">
                                            <span class="font-bold text-slate-800">{{ $ing->name }}</span>
                                            <span class="block text-xs text-slate-400 font-mono">{{ $ing->sku }}</span>
                                        </td>
                                        <td class="p-4 text-xs font-semibold text-slate-500 uppercase">{{ $ing->category->value }}</td>
                                        <td class="p-4 text-right font-mono font-medium text-slate-700">
                                            {{ number_format((float)$ing->current_stock, 2) }}
                                        </td>
                                        <td class="p-4 text-right text-xs text-slate-500">{{ $ing->base_unit->value }}</td>
                                        <td class="p-4 text-right">
                                            <input type="number" step="0.001" name="counted_quantities[{{ $ing->id }}]" value="{{ (float)$ing->current_stock }}" required class="w-full text-right rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500 text-sm font-bold">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="p-5 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50/50">
                        <a href="{{ route('resto.stock-counts.index') }}" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200 transition-all">Batal</a>
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-amber-600 to-rose-600 hover:from-amber-700 hover:to-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-md">
                            Simpan Hasil Opname & Ajukan Approval
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
