<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Daftar Tenant Mitra Mall
                </h2>
                <p class="text-sm text-slate-500 mt-1">Kelola direktori toko, restoran, dan gerai mitra komersial Duta Mall</p>
            </div>
            <div>
                <a href="{{ route('mall.tenants.create') }}" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Daftarkan Tenant Baru</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            <!-- Search & Filter Bar -->
            <div class="p-4 bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('mall.tenants.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama brand / PT / PIC..." class="rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500 w-64">
                    </div>

                    <div>
                        <select name="category" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Kategori Bisnis</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->value }}" {{ $selectedCategory === $cat->value ? 'selected' : '' }}>{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">
                        Filter
                    </button>
                </form>
            </div>

            <!-- Table of Tenants -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/70 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                <th class="p-4">Brand & Perusahaan</th>
                                <th class="p-4">Kategori Bisnis</th>
                                <th class="p-4">Unit Tersewa Aktif</th>
                                <th class="p-4">PIC & Kontak</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($tenants as $t)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="p-4">
                                        <div class="font-bold text-slate-900 text-base">{{ $t->brand_name }}</div>
                                        <div class="text-xs text-slate-400 mt-0.5">{{ $t->company_name }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700">
                                            {{ $t->category->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        @if($t->activeLeases->isNotEmpty())
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                @foreach($t->activeLeases as $al)
                                                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        {{ $al->unit?->unit_number }} (Lt. {{ $al->unit?->floor }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400 italic">Belum ada unit aktif</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-xs">
                                        <div class="font-bold text-slate-700">{{ $t->pic_name }}</div>
                                        <div class="text-slate-500 font-mono">{{ $t->pic_phone }}</div>
                                        <div class="text-slate-400">{{ $t->pic_email }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold {{ $t->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $t->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="{{ route('mall.tenants.show', $t) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-all">
                                            Profil &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-10 text-center text-slate-400 italic">Belum ada data tenant mitra terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($tenants->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $tenants->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
