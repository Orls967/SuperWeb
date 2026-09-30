<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight flex items-center gap-2">
                    <span class="p-2 bg-gradient-to-tr from-amber-600 to-rose-600 rounded-xl text-white shadow-md shadow-rose-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </span>
                    <span>Stock Opname Fisik Bahan Baku</span>
                </h2>
                <p class="text-sm text-slate-500 mt-1">Audit fisik bahan baku di gudang/dapur, rekonsiliasi selisih sistem vs fisik & approval manager</p>
            </div>

            <!-- Outlet Filter & Create Opname Button -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('resto.stock-counts.index') }}" class="flex items-center">
                    <select name="outlet_id" onchange="this.form.submit()" class="text-sm font-semibold rounded-xl border-slate-300 bg-white text-slate-700 shadow-sm focus:border-rose-500 focus:ring-rose-500">
                        <option value="">Semua Outlet</option>
                        @foreach($outlets as $ot)
                            <option value="{{ $ot->id }}" {{ $selectedOutletId == $ot->id ? 'selected' : '' }}>
                                {{ $ot->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('resto.stock-counts.create') }}" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-rose-600 hover:from-amber-700 hover:to-rose-700 text-white rounded-xl text-sm font-bold shadow-md shadow-rose-500/20 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    <span>+ Mulai Stock Opname</span>
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

            <!-- Opname List Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-800">Riwayat Sesi Stock Opname</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Daftar pemeriksaan fisik persediaan bahan baku per outlet</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">Tanggal</th>
                                <th class="p-4">Outlet</th>
                                <th class="p-4">Petugas Penghitung</th>
                                <th class="p-4">Approver</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($counts as $count)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 font-mono font-bold text-slate-900">
                                        <a href="{{ route('resto.stock-counts.show', $count) }}" class="hover:text-rose-600">
                                            {{ $count->date->format('d/m/Y') }}
                                        </a>
                                        <span class="block text-[11px] text-slate-400 font-sans font-normal">{{ $count->created_at->format('H:i') }} WIB</span>
                                    </td>
                                    <td class="p-4 font-semibold text-slate-800">{{ $count->outlet?->name }}</td>
                                    <td class="p-4 text-slate-600">{{ $count->counter?->name }}</td>
                                    <td class="p-4 text-slate-600">{{ $count->approver?->name ?: 'Menunggu Approval' }}</td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            {{ $count->status->value === 'approved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $count->status->value === 'submitted' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $count->status->value === 'draft' ? 'bg-slate-100 text-slate-800' : '' }}
                                            {{ $count->status->value === 'rejected' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ $count->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="{{ route('resto.stock-counts.show', $count) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 font-semibold text-xs text-slate-700 transition-all">
                                            Lihat Hasil Selisih &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">
                                        Belum ada data Stock Opname. Klik "+ Mulai Stock Opname" untuk melakukan penghitungan fisik bahan baku.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($counts->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $counts->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
