<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-slate-900/60 backdrop-blur-md p-6 rounded-2xl border border-slate-800 shadow-xl">
            <div>
                <div class="flex items-center gap-3">
                    <span class="p-2.5 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Jaringan Outlet & Dapur Sentral</h1>
                        <p class="text-sm text-slate-400">Kelola cabang rumah makan Padang RM Sari Ranah & fasilitas Dapur Sentral</p>
                    </div>
                </div>
            </div>
            <div>
                <a href="{{ route('resto.outlets.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-medium shadow-lg shadow-amber-500/25 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Cabang / Dapur
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Outlets Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($outlets as $outlet)
                <div class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 flex flex-col justify-between hover:border-slate-700 transition shadow-lg">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-lg {{ $outlet->isCentralKitchen() ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                    {{ $outlet->type->label() }}
                                </span>
                                <h3 class="text-lg font-bold text-white mt-2">{{ $outlet->name }}</h3>
                                <p class="text-xs text-slate-400 font-mono">Kode: {{ $outlet->code }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $outlet->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                {{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <div class="text-sm text-slate-300 space-y-1.5 pt-2 border-t border-slate-800">
                            <p class="flex items-center gap-2 text-slate-400">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>{{ $outlet->address }}, {{ $outlet->city }}</span>
                            </p>
                            @if($outlet->mall_unit_ref)
                                <p class="flex items-center gap-2 text-indigo-300 text-xs">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"></path></svg>
                                    <span>Unit Mall: <strong>{{ $outlet->mall_unit_ref }}</strong></span>
                                </p>
                            @endif
                            <p class="flex items-center gap-2 text-slate-400 text-xs">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Operasional: {{ substr($outlet->opens_at, 0, 5) }} - {{ substr($outlet->closes_at, 0, 5) }} WITA</span>
                            </p>
                            @if(!$outlet->isCentralKitchen())
                                <p class="flex items-center gap-2 text-slate-400 text-xs">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Kapasitas: {{ $outlet->seats }} Kursi</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="pt-4 mt-4 border-t border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-medium">
                            Staff ditugaskan: {{ $outlet->staff_assignments_count }}
                        </span>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('resto.outlets.edit', $outlet) }}" class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition" title="Edit Cabang">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <a href="{{ route('resto.outlets.show', $outlet) }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-amber-400 hover:text-amber-300 font-medium transition">
                                Detail →
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-12 text-center bg-slate-900/40 rounded-2xl border border-slate-800 text-slate-400">
                    Belum ada outlet atau dapur sentral yang didaftarkan.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
