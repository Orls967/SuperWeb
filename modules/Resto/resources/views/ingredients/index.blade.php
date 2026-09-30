<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Katalog Bahan Baku & Konversi</h1>
                        <p class="text-sm text-slate-400">Master data bahan mentah, moving average cost per outlet, dan rasio konversi satuan dasar</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.ingredients.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium shadow-lg shadow-emerald-500/25 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Bahan Baku
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Filter & Search -->
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('resto.ingredients.index') }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ !request('category') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800/80 text-slate-400 hover:text-white' }}">
                    Semua Kategori
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('resto.ingredients.index', ['category' => $cat->value]) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition {{ request('category') === $cat->value ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800/80 text-slate-400 hover:text-white' }}">
                        {{ $cat->label() }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('resto.ingredients.index') }}" class="flex items-center gap-2">
                @if(request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / SKU bahan..." class="bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-4 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 w-56">
                    <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 rounded-xl font-medium">Filter</button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Ingredients Table (3 cols) -->
            <div class="lg:col-span-3 bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4 font-semibold">SKU & Bahan Baku</th>
                                <th class="py-3.5 px-3 font-semibold">Kategori</th>
                                <th class="py-3.5 px-3 font-semibold">Satuan Dasar</th>
                                <th class="py-3.5 px-4 font-semibold">Moving Avg Cost</th>
                                <th class="py-3.5 px-3 font-semibold">Sifat Bahan</th>
                                <th class="py-3.5 px-4 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse($ingredients as $ing)
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-white text-sm">{{ $ing->name }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono">{{ $ing->sku }}</div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-800 text-slate-300">
                                            {{ $ing->category->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-emerald-400">
                                        {{ $ing->base_unit->label() }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $firstCost = $ing->costs->first();
                                            $avg = $firstCost ? (float) $firstCost->moving_avg_cost_per_base_unit : 0;
                                        @endphp
                                        <div class="font-mono text-white">
                                            @if($ing->base_unit->value === 'gram')
                                                Rp {{ number_format($avg, 2) }} /g <span class="text-slate-500 text-[10px]">(Rp {{ number_format($avg * 1000, 0, ',', '.') }}/kg)</span>
                                            @elseif($ing->base_unit->value === 'ml')
                                                Rp {{ number_format($avg, 2) }} /ml <span class="text-slate-500 text-[10px]">(Rp {{ number_format($avg * 1000, 0, ',', '.') }}/L)</span>
                                            @else
                                                Rp {{ number_format($avg, 0, ',', '.') }} /pcs
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($ing->is_perishable)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                                Mudah Rusak ({{ $ing->shelf_life_hours }} jam)
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-400">
                                                Tahan Lama
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="{{ route('resto.ingredients.edit', $ing) }}" class="text-amber-400 hover:text-amber-300 font-medium text-xs">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500">
                                        Tidak ada data bahan baku yang cocok.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($ingredients->hasPages())
                    <div class="p-4 border-t border-slate-800">
                        {{ $ingredients->links() }}
                    </div>
                @endif
            </div>

            <!-- Unit Conversions Sidebar (1 col) -->
            <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-5 space-y-4 shadow-xl">
                <div>
                    <h3 class="text-sm font-bold text-white">Tabel Konversi Satuan</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Konversi otomatis ke satuan dasar (g, ml, pcs)</p>
                </div>

                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    @forelse($conversions as $conv)
                        <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800/80 text-xs">
                            <div class="flex items-center justify-between text-white font-medium">
                                <span>1 {{ $conv->from_unit }}</span>
                                <span class="text-emerald-400 font-mono">= {{ (float) $conv->to_base_factor }} base</span>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $conv->label }}</div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 text-center py-4">Belum ada konversi khusus.</p>
                    @endforelse
                </div>

                <!-- Quick Add Conversion -->
                <form action="{{ route('resto.ingredients.conversions.store') }}" method="POST" class="pt-4 border-t border-slate-800 space-y-3">
                    @csrf
                    <p class="text-xs font-semibold text-slate-300">Tambah Konversi Cepat</p>
                    <div>
                        <input type="text" name="from_unit" placeholder="Dari Satuan (misal: ikat, karung)" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500" required>
                    </div>
                    <div>
                        <input type="number" step="0.000001" name="to_base_factor" placeholder="Faktor Kali Dasar (misal: 250)" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500" required>
                    </div>
                    <div>
                        <input type="text" name="label" placeholder="Keterangan (misal: 1 ikat = 250 gram)" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500" required>
                    </div>
                    <button type="submit" class="w-full py-1.5 bg-slate-800 hover:bg-slate-700 text-xs text-emerald-400 font-medium rounded-lg transition">
                        + Simpan Konversi
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
