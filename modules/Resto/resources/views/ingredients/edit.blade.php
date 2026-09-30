<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">
        <div>
            <a href="{{ route('resto.ingredients.index') }}" class="text-sm text-slate-400 hover:text-white flex items-center gap-1 mb-2">
                ← Kembali ke Katalog Bahan Baku
            </a>
            <h1 class="text-2xl font-bold text-white tracking-tight">Edit Bahan Baku: {{ $ingredient->name }}</h1>
        </div>

        <form action="{{ route('resto.ingredients.update', $ingredient) }}" method="POST" class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-6 shadow-xl">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">SKU Bahan Baku *</label>
                    <input type="text" name="sku" value="{{ old('sku', $ingredient->sku) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition" required>
                    @error('sku') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nama Bahan Baku *</label>
                    <input type="text" name="name" value="{{ old('name', $ingredient->name) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition" required>
                    @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kategori Bahan *</label>
                    <select name="category" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-emerald-500 transition" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->value }}" {{ old('category', $ingredient->category->value) === $category->value ? 'selected' : '' }}>
                                {{ $category->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Satuan Dasar Pengukuran *</label>
                    <select name="base_unit" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-emerald-500 transition" required>
                        @foreach($baseUnits as $bu)
                            <option value="{{ $bu->value }}" {{ old('base_unit', $ingredient->base_unit->value) === $bu->value ? 'selected' : '' }}>
                                {{ $bu->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Batas Minimum Stok (Satuan Dasar) *</label>
                    <input type="number" step="0.000001" name="min_stock_base_unit" value="{{ old('min_stock_base_unit', (float) $ingredient->min_stock_base_unit) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-emerald-500 transition" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Masa Kedaluwarsa (Jam, Opsional)</label>
                    <input type="number" name="shelf_life_hours" value="{{ old('shelf_life_hours', $ingredient->shelf_life_hours) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-800">
                <input type="checkbox" id="is_perishable" name="is_perishable" value="1" {{ old('is_perishable', $ingredient->is_perishable) ? 'checked' : '' }} class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-emerald-500 focus:ring-emerald-500">
                <label for="is_perishable" class="text-sm font-medium text-slate-300">Bahan Mudah Rusak / Basi (Perishable)</label>
            </div>

            <!-- Costs Per Outlet -->
            <div class="pt-4 border-t border-slate-800 space-y-4">
                <h3 class="text-sm font-bold text-white">Biaya per Satuan Dasar (Moving Average Cost)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($outlets as $outlet)
                        @php
                            $costRec = $ingredient->costs->where('outlet_id', $outlet->id)->first();
                            $currentCost = $costRec ? (float) $costRec->moving_avg_cost_per_base_unit : 0;
                        @endphp
                        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                            <span class="text-xs font-semibold text-amber-400">{{ $outlet->name }}</span>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs text-slate-500">Rp</span>
                                <input type="number" step="0.000001" name="costs[{{ $outlet->id }}]" value="{{ old('costs.'.$outlet->id, $currentCost) }}" class="w-full bg-slate-900 border border-slate-800 rounded-lg pl-9 pr-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                <a href="{{ route('resto.ingredients.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white text-sm font-medium shadow-lg shadow-emerald-500/25 transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
