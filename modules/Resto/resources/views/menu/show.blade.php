<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div>
                <a href="{{ route('resto.menu.index') }}" class="text-sm text-slate-400 hover:text-white flex items-center gap-1 mb-2">
                    ← Kembali ke Daftar Menu
                </a>
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-lg {{ $item->service_style->value === 'hidang' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                        {{ $item->service_style->label() }}
                    </span>
                    <h1 class="text-2xl font-bold text-white tracking-tight">{{ $item->name }}</h1>
                    <span class="text-xs text-slate-400 font-mono">({{ $item->sku }})</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.menu.edit', $item) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition shadow-lg shadow-amber-500/25">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit Menu & Resep
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Info Card -->
            <div class="space-y-6">
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-4 shadow-lg">
                    <h3 class="text-base font-bold text-white border-b border-slate-800 pb-3">Detail Menu</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-slate-400">Kategori</dt>
                            <dd class="text-slate-200 font-medium mt-0.5">{{ $item->category?->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Harga Jual Dine-In</dt>
                            <dd class="text-xl font-bold text-white font-mono mt-0.5">Rp {{ number_format($item->base_price, 0, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Harga Bungkus / Takeaway</dt>
                            <dd class="text-slate-200 font-mono mt-0.5">Rp {{ number_format($item->takeaway_price, 0, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Tingkat Kepedasan</dt>
                            <dd class="text-amber-400 mt-0.5">
                                @for($i = 0; $i < $item->spice_level; $i++) 🌶️ @endfor
                                @if($item->spice_level === 0) <span class="text-slate-500">Tidak Pedas</span> @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Status Operasional</dt>
                            <dd class="mt-0.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $item->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                    {{ $item->is_active ? 'Aktif di POS' : 'Nonaktif' }}
                                </span>
                            </dd>
                        </div>
                        @if($item->description)
                            <div>
                                <dt class="text-xs text-slate-400">Deskripsi</dt>
                                <dd class="text-slate-300 text-xs mt-0.5">{{ $item->description }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- Margin & HPP Summary Card -->
                <div class="bg-gradient-to-br from-slate-950 to-slate-900 rounded-2xl border border-slate-800 p-6 space-y-4 shadow-lg">
                    <h3 class="text-base font-bold text-white border-b border-slate-800 pb-3">Kalkulasi Keuangan</h3>
                    @if($costData['has_recipe'])
                        @php
                            $hpp = $costData['cost_per_portion_idr'];
                            $price = $item->base_price;
                            $margin = (float) $costData['margin_percent']->toFloat();
                        @endphp
                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400">HPP / Porsi:</span>
                                <span class="font-mono font-bold text-white">Rp {{ number_format($hpp, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400">Margin Kotor:</span>
                                <span class="font-mono font-bold {{ $margin < 30 ? 'text-amber-400' : 'text-emerald-400' }}">
                                    {{ number_format($margin, 1) }}%
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400">Saran Harga Jual:</span>
                                <span class="font-mono font-bold text-indigo-400">Rp {{ number_format($costData['suggested_price'], 0, ',', '.') }}</span>
                            </div>

                            @if($hpp > $price)
                                <div class="p-3 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-300 text-xs">
                                    ⚠️ <strong>RUGI:</strong> HPP melebihi harga jual sebesar Rp {{ number_format($hpp - $price, 0, ',', '.') }}.
                                </div>
                            @elseif($margin < 30)
                                <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-300 text-xs">
                                    ⚠️ <strong>MARGIN RENDAH:</strong> Margin di bawah 30% berisiko rugi saat ada waste etalase.
                                </div>
                            @else
                                <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-xs">
                                    ✓ <strong>MARGIN SEHAT:</strong> Margin di atas 30% mendukung keuntungan optimal.
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-xs text-slate-500 italic">Menu ini belum memiliki resep aktif.</p>
                    @endif
                </div>
            </div>

            <!-- Right: Recipe Details (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                @if($item->recipe)
                    <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-4 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                            <h3 class="text-base font-bold text-white">Komposisi Resep (BOM)</h3>
                            <span class="text-xs text-slate-400 font-mono">
                                Target: {{ (float)$item->recipe->expected_portions }} porsi | Waste: {{ (float)$item->recipe->waste_percent }}%
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-slate-400 uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3">Tipe</th>
                                        <th class="py-2.5 px-3">Bahan / Sub-Resep</th>
                                        <th class="py-2.5 px-3 text-right">Kuantitas</th>
                                        <th class="py-2.5 px-3 text-right">Biaya Satuan</th>
                                        <th class="py-2.5 px-3 text-right">Total Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/80">
                                    @forelse($costData['lines'] as $line)
                                        <tr class="hover:bg-slate-800/40">
                                            <td class="py-2.5 px-3">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] {{ $line['type'] === 'ingredient' ? 'bg-slate-800 text-slate-300' : 'bg-indigo-500/10 text-indigo-400' }}">
                                                    {{ $line['type'] === 'ingredient' ? 'Bahan' : 'Sub-Resep' }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 font-semibold text-white">
                                                {{ $line['name'] }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono">
                                                {{ (float) $line['qty'] }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono text-slate-400">
                                                Rp {{ number_format((float)$line['unit_cost'], 2) }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono text-white font-semibold">
                                                Rp {{ number_format((float)$line['line_cost'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-4 text-center text-slate-500">Tidak ada baris bahan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="border-t border-slate-700 bg-slate-950/60 font-bold">
                                    <tr>
                                        <td colspan="4" class="py-3 px-3 text-right text-slate-300">Total Biaya Batch:</td>
                                        <td class="py-3 px-3 text-right text-emerald-400 font-mono text-sm">
                                            Rp {{ number_format((float)$costData['batch_cost'], 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        @if($item->recipe->instructions)
                            <div class="pt-4 border-t border-slate-800">
                                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Instruksi Memasak / Standar Dapur:</h4>
                                <p class="text-xs text-slate-300 leading-relaxed bg-slate-950 p-4 rounded-xl border border-slate-800 font-sans">
                                    {{ $item->recipe->instructions }}
                                </p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-slate-900/40 rounded-2xl border border-slate-800 p-8 text-center text-slate-400">
                        <p>Menu ini belum memiliki resep BOM.</p>
                        <a href="{{ route('resto.menu.edit', $item) }}" class="mt-3 inline-block px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-medium transition">
                            + Tambah Resep Sekarang
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
