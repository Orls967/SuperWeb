<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Direktori Tenant Duta Mall
                </h2>
                <p class="text-sm text-slate-500 mt-1">Temukan toko, restoran kuliner, bengkel otomotif, dan hiburan favorit di Duta Mall</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('mall.site-plan.index') }}" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50">
                    Denah Site Plan &rarr;
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Search & Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                <form method="GET" action="{{ route('mall.directory.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                    <div class="relative flex-1 w-full">
                        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama gerai, kuliner, toko (misal: Sari Ranah, Starbucks)..."
                               class="w-full pl-10 pr-4 py-3 rounded-xl border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <div class="absolute left-3.5 top-3.5 text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <select name="floor" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-semibold text-slate-700 py-3">
                            <option value="">Semua Lantai</option>
                            @foreach($floors as $fl)
                                <option value="{{ $fl }}" {{ $selectedFloor === $fl ? 'selected' : '' }}>Lantai {{ $fl }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 whitespace-nowrap">
                            Cari Toko
                        </button>
                    </div>
                </form>

                <!-- Category Chips -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1">
                    <a href="{{ route('mall.directory.index', array_filter(['floor' => $selectedFloor, 'q' => $search])) }}"
                       class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all whitespace-nowrap
                       {{ empty($selectedCategory) ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Semua Kategori
                    </a>
                    @foreach($categories as $c)
                        <a href="{{ route('mall.directory.index', array_filter(['category' => $c->value, 'floor' => $selectedFloor, 'q' => $search])) }}"
                           class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-all whitespace-nowrap
                           {{ $selectedCategory === $c->value ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $c->label() }}
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Tenant Store Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-6">
                @forelse($tenants as $t)
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:shadow-md hover:border-blue-200 transition-all">
                        <div>
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $t->category->label() }}
                                </span>
                                @if($t->activeLeases->isNotEmpty())
                                    <span class="text-xs font-mono font-black text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-md">
                                        Lt. {{ $t->activeLeases->first()->unit?->floor }} &bull; Unit {{ $t->activeLeases->first()->unit?->unit_number }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="text-lg font-black text-slate-900">{{ $t->brand_name }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $t->company_name }}</p>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Buka 10:00 - 22:00 WITA</span>
                            </div>
                            <span class="font-bold text-blue-600">Duta Mall</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400 italic">
                        Tidak ada toko atau tenant yang cocok dengan kriteria pencarian Anda.
                    </div>
                @endforelse
            </div>

            @if($tenants->hasPages())
                <div class="p-4 bg-white rounded-2xl shadow-sm border border-slate-200">
                    {{ $tenants->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
