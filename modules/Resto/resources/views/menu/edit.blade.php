<x-app-layout>
    @php
        $recipe = $item->recipe;
        $initialLines = [];
        if ($recipe) {
            foreach ($recipe->lines as $line) {
                $initialLines[] = [
                    'line_type' => $line->line_type->value,
                    'id' => $line->line_type->value === 'ingredient' ? $line->ingredient_id : $line->sub_recipe_id,
                    'qty_base_unit' => (float) $line->qty_base_unit,
                    'note' => $line->note ?? '',
                ];
            }
        }
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6"
         x-data="recipeBuilder({
             endpoint: '{{ route('resto.menu.calculate-cost') }}',
             csrf: '{{ csrf_token() }}',
             ingredients: {{ Js::from($ingredients->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->base_unit->value])) }},
             subRecipes: {{ Js::from($subRecipes->map(fn($r) => ['id' => $r->id, 'name' => $r->sub_recipe_name, 'unit' => $r->yield_unit])) }},
             sellingPrice: {{ old('base_price', $item->base_price) }},
             expectedPortions: {{ old('expected_portions', $recipe ? (float)$recipe->expected_portions : 10) }},
             wastePercent: {{ old('waste_percent', $recipe ? (float)$recipe->waste_percent : 0) }},
             initialLines: {{ Js::from($initialLines) }}
         })">
        <div>
            <a href="{{ route('resto.menu.index') }}" class="text-sm text-slate-400 hover:text-white flex items-center gap-1 mb-2">
                ← Kembali ke Daftar Menu
            </a>
            <h1 class="text-2xl font-bold text-white tracking-tight">Edit Menu: {{ $item->name }}</h1>
        </div>

        <form action="{{ route('resto.menu.update', $item) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Card 1: Informasi Dasar Menu -->
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-6 shadow-xl">
                <h3 class="text-base font-bold text-white border-b border-slate-800 pb-3 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs font-mono">1</span>
                    Informasi Menu & Harga Jual
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">SKU Menu *</label>
                        <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                        @error('sku') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nama Menu *</label>
                        <input type="text" name="name" value="{{ old('name', $item->name) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                        @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kategori Menu *</label>
                        <select name="category_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id', $item->category_id) == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Gaya Penyajian (Service Style) *</label>
                        <select name="service_style" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                            @foreach($serviceStyles as $ss)
                                <option value="{{ $ss->value }}" {{ old('service_style', $item->service_style->value) === $ss->value ? 'selected' : '' }}>
                                    {{ $ss->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Tingkat Kepedasan (0 - 5)</label>
                        <input type="number" name="spice_level" value="{{ old('spice_level', $item->spice_level) }}" min="0" max="5" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Harga Jual Dine-In (Rp) *</label>
                        <input type="number" name="base_price" x-model.number="sellingPrice" @input.debounce.300ms="recalculate()" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white font-mono text-base font-bold focus:outline-none focus:border-amber-500 transition" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Harga Bungkus / Takeaway (Rp)</label>
                        <input type="number" name="takeaway_price" value="{{ old('takeaway_price', $item->takeaway_price) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white font-mono focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Status Produk</label>
                        <div class="flex items-center gap-4 pt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }} class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-amber-500 focus:ring-amber-500">
                                <span>Aktif di POS</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                <input type="checkbox" name="is_halal_certified" value="1" {{ old('is_halal_certified', $item->is_halal_certified) ? 'checked' : '' }} class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-emerald-500 focus:ring-emerald-500">
                                <span>Sertifikasi Halal</span>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Deskripsi Menu</label>
                        <textarea name="description" rows="2" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">{{ old('description', $item->description) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Card 2: Interactive Recipe Builder (BOM) -->
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs font-mono">2</span>
                        Penyusunan Resep Berlapis (Bill of Materials) & Kalkulasi HPP
                    </h3>
                    <input type="hidden" name="has_recipe" value="1">
                </div>

                <!-- Recipe Parameters -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 bg-slate-950/60 p-4 rounded-xl border border-slate-800/80">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Target Yield Batch</label>
                        <input type="number" step="0.000001" name="yield_qty" value="{{ old('yield_qty', $recipe ? (float)$recipe->yield_qty : 10) }}" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Satuan Yield</label>
                        <input type="text" name="yield_unit" value="{{ old('yield_unit', $recipe ? $recipe->yield_unit : 'porsi') }}" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Porsi Yang Dihasilkan *</label>
                        <input type="number" step="0.000001" name="expected_portions" x-model.number="expectedPortions" @input.debounce.300ms="recalculate()" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Estimasi Waste (%)</label>
                        <input type="number" step="0.01" name="waste_percent" x-model.number="wastePercent" @input.debounce.300ms="recalculate()" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
                    </div>
                </div>

                <!-- Dynamic Recipe Lines -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-300 uppercase tracking-wider">Komposisi Bahan & Bumbu Dasar</label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="addLine('ingredient')" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-emerald-400 text-xs font-medium rounded-lg transition flex items-center gap-1">
                                + Bahan Baku
                            </button>
                            <button type="button" @click="addLine('sub_recipe')" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-indigo-400 text-xs font-medium rounded-lg transition flex items-center gap-1">
                                + Bumbu Dasar (Sub-Resep)
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(line, idx) in lines" :key="idx">
                            <div class="grid grid-cols-12 gap-2 p-3 bg-slate-950 rounded-xl border border-slate-800/80 items-center">
                                <div class="col-span-3">
                                    <select :name="'recipe_lines[' + idx + '][line_type]'" x-model="line.line_type" @change="recalculate()" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-emerald-500">
                                        <option value="ingredient">Bahan Mentah</option>
                                        <option value="sub_recipe">Sub-Resep</option>
                                    </select>
                                </div>
                                <div class="col-span-4">
                                    <template x-if="line.line_type === 'ingredient'">
                                        <select :name="'recipe_lines[' + idx + '][id]'" x-model="line.id" @change="recalculate()" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                                            <option value="">-- Pilih Bahan --</option>
                                            <template x-for="ing in ingredients" :key="ing.id">
                                                <option :value="ing.id" :selected="line.id == ing.id" x-text="ing.name + ' (' + ing.unit + ')'"></option>
                                            </template>
                                        </select>
                                    </template>
                                    <template x-if="line.line_type === 'sub_recipe'">
                                        <select :name="'recipe_lines[' + idx + '][id]'" x-model="line.id" @change="recalculate()" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-indigo-300 focus:outline-none focus:border-indigo-500">
                                            <option value="">-- Pilih Sub-Resep --</option>
                                            <template x-for="sub in subRecipes" :key="sub.id">
                                                <option :value="sub.id" :selected="line.id == sub.id" x-text="sub.name + ' (' + sub.unit + ')'"></option>
                                            </template>
                                        </select>
                                    </template>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.000001" :name="'recipe_lines[' + idx + '][qty_base_unit]'" x-model.number="line.qty_base_unit" @input.debounce.300ms="recalculate()" placeholder="Qty dasar" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500" required>
                                </div>
                                <div class="col-span-2">
                                    <input type="text" :name="'recipe_lines[' + idx + '][note]'" x-model="line.note" placeholder="Catatan (opsional)" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-400 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div class="col-span-1 text-right">
                                    <button type="button" @click="removeLine(idx)" class="p-1.5 text-slate-500 hover:text-rose-400 transition" title="Hapus baris">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Real-Time Cost & Margin Analysis Panel -->
                <div class="bg-gradient-to-br from-slate-950 to-slate-900 p-5 rounded-2xl border border-slate-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>Hasil Analisis HPP & Margin Real-Time</span>
                            <span x-show="loading" class="text-amber-400 text-[10px] animate-pulse">Menghitung...</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                            <span class="text-[11px] text-slate-400 block">Biaya Batch Total</span>
                            <span class="text-base font-bold text-white font-mono" x-text="formatIdr(costData.batch_cost)"></span>
                        </div>
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                            <span class="text-[11px] text-slate-400 block">HPP per Porsi</span>
                            <span class="text-base font-bold font-mono" :class="costData.warning === 'loss' ? 'text-rose-400' : 'text-emerald-400'" x-text="formatIdr(costData.cost_per_portion_idr)"></span>
                        </div>
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                            <span class="text-[11px] text-slate-400 block">Margin Laba</span>
                            <div class="flex items-center gap-2">
                                <span class="text-base font-bold font-mono" :class="costData.margin_percent < 30 ? 'text-amber-400' : 'text-emerald-400'" x-text="costData.margin_percent.toFixed(1) + '%'"></span>
                                <span x-show="costData.warning === 'loss'" class="px-1.5 py-0.5 text-[9px] font-bold bg-rose-500/20 text-rose-300 rounded border border-rose-500/30">RUGI</span>
                                <span x-show="costData.warning === 'low_margin'" class="px-1.5 py-0.5 text-[9px] font-semibold bg-amber-500/20 text-amber-300 rounded border border-amber-500/30">&lt; 30%</span>
                            </div>
                        </div>
                        <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                            <span class="text-[11px] text-slate-400 block">Saran Harga Jual (Markup 50%)</span>
                            <span class="text-base font-bold text-indigo-400 font-mono" x-text="formatIdr(costData.suggested_price)"></span>
                        </div>
                    </div>

                    <!-- Warning Alert Banner -->
                    <div x-show="costData.warning === 'loss'" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span><strong>PERINGATAN: HPP melebihi Harga Jual!</strong> Penjualan menu ini akan menghasilkan kerugian per porsi. Sesuaikan resep atau naikkan harga jual minimal ke <strong x-text="formatIdr(costData.suggested_price)"></strong>.</span>
                    </div>

                    <div x-show="costData.warning === 'low_margin'" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span><strong>PERINGATAN: Margin laba di bawah 30%!</strong> Margin saat ini (<span x-text="costData.margin_percent.toFixed(1) + '%'"></span>) berisiko tergerus biaya operasional dan waste etalase.</span>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('resto.menu.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-sm font-medium shadow-lg shadow-amber-500/25 transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
