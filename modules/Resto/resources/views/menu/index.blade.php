<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Daftar Menu RM Sari Ranah</h1>
                        <p class="text-sm text-slate-400">Katalog menu masakan Padang, resep bertingkat BOM, analisis HPP, dan margin laba</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.menu.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-medium shadow-lg shadow-amber-500/25 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Menu Baru
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <!-- Categories -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('resto.menu.index', array_merge(request()->except('category', 'page'))) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ !request('category') ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800/80 text-slate-400 hover:text-white' }}">
                        Semua ({{ \Modules\Resto\Domain\Models\MenuItem::count() }})
                    </a>
                    @foreach($categories as $cat)
                        <a href="{{ route('resto.menu.index', array_merge(request()->except('page'), ['category' => $cat->id])) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ request('category') == $cat->id ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800/80 text-slate-400 hover:text-white' }}">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Outlet Cost Selector & Search -->
                <form method="GET" action="{{ route('resto.menu.index') }}" class="flex items-center gap-2">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <select name="outlet_id" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-300 focus:outline-none focus:border-amber-500">
                        <option value="">Biaya Rata-Rata Global</option>
                        @foreach($outlets as $out)
                            <option value="{{ $out->id }}" {{ $outletId == $out->id ? 'selected' : '' }}>
                                Biaya Cabang: {{ $out->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari menu / SKU..." class="bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-4 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 w-48">
                        <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 rounded-xl font-medium">Filter</button>
                </form>
            </div>
        </div>

        <!-- Menu Table -->
        <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 font-semibold">SKU & Nama Menu</th>
                            <th class="py-3.5 px-3 font-semibold">Penyajian</th>
                            <th class="py-3.5 px-3 font-semibold">Kategori</th>
                            <th class="py-3.5 px-4 font-semibold">Harga Jual</th>
                            <th class="py-3.5 px-4 font-semibold">HPP / Porsi</th>
                            <th class="py-3.5 px-3 font-semibold">Margin Laba</th>
                            <th class="py-3.5 px-4 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @forelse($itemsWithCost as $row)
                            @php
                                $item = $row['model'];
                                $warning = $row['warning'];
                            @endphp
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <div class="font-bold text-white text-sm flex items-center gap-2">
                                                <span>{{ $item->name }}</span>
                                                @if(!$item->is_active)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-500/10 text-rose-400">Nonaktif</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-500 font-mono">{{ $item->sku }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $item->service_style->value === 'hidang' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                                        {{ $item->service_style->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-400">
                                    {{ $item->category?->name }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-mono text-white font-semibold">Rp {{ number_format($row['selling_price'], 0, ',', '.') }}</div>
                                    @if($item->takeaway_price != $row['selling_price'])
                                        <div class="text-[10px] text-slate-400">Bungkus: Rp {{ number_format($item->takeaway_price, 0, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($row['cost_data']['has_recipe'])
                                        <div class="font-mono font-medium {{ $warning === 'loss' ? 'text-rose-400 font-bold' : 'text-slate-300' }}">
                                            Rp {{ number_format($row['cost_per_portion_idr'], 0, ',', '.') }}
                                        </div>
                                        <div class="text-[10px] text-slate-500">BOM terperinci</div>
                                    @else
                                        <span class="text-slate-500 italic text-[11px]">Belum ada resep</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    @if($row['cost_data']['has_recipe'])
                                        @if($warning === 'loss')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30 animate-pulse">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                RUGI ({{ number_format($row['margin_percent'], 1) }}%)
                                            </span>
                                        @elseif($warning === 'low_margin')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                MARGIN RENDAH ({{ number_format($row['margin_percent'], 1) }}%)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
                                                +{{ number_format($row['margin_percent'], 1) }}%
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-500">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('resto.menu.show', $item) }}" class="text-indigo-400 hover:text-indigo-300 font-medium text-xs">
                                            Detail
                                        </a>
                                        <span class="text-slate-700">|</span>
                                        <a href="{{ route('resto.menu.edit', $item) }}" class="text-amber-400 hover:text-amber-300 font-medium text-xs">
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500">
                                    Tidak ada data menu masakan Padang yang cocok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($items->hasPages())
                <div class="p-4 border-t border-slate-800">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
