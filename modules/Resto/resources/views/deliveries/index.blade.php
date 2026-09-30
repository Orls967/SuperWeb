<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Pesanan Delivery & Bungkus
                </h2>
                <p class="text-sm text-slate-500 mt-1">Pantau pesanan antar jarak jauh, kemasan nasi bungkus, dan status kurir</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.deliveries.create') }}" class="px-5 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-teal-500/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Pesan Antar / Delivery Baru</span>
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

            <!-- Filter Bar -->
            <div class="p-4 bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('resto.deliveries.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div>
                        <select name="outlet_id" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua Outlet</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}" {{ $selectedOutletId === $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua Status</option>
                            <option value="preparing" {{ $selectedStatus === 'preparing' ? 'selected' : '' }}>Sedang Disiapkan</option>
                            <option value="on_the_way" {{ $selectedStatus === 'on_the_way' ? 'selected' : '' }}>Dalam Pengantaran</option>
                            <option value="delivered" {{ $selectedStatus === 'delivered' ? 'selected' : '' }}>Terkirim</option>
                            <option value="failed" {{ $selectedStatus === 'failed' ? 'selected' : '' }}>Gagal Kirim</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <th class="p-4">No. Order & Waktu</th>
                                <th class="p-4">Penerima & Alamat</th>
                                <th class="p-4">Outlet & Jarak</th>
                                <th class="p-4 text-right">Ongkir & Kemasan</th>
                                <th class="p-4 text-right">Total Tagihan</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($deliveries as $item)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="p-4">
                                        <div class="font-mono font-bold text-teal-700">#{{ $item->order?->number }}</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">{{ $item->created_at->format('d/m/Y H:i') }} WIB</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">{{ $item->recipient_name }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $item->recipient_phone }}</div>
                                        <div class="text-xs text-slate-400 truncate max-w-xs mt-0.5">{{ $item->delivery_address }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-700">{{ $item->outlet?->name }}</div>
                                        <div class="text-xs text-slate-400">{{ (float) $item->distance_km }} km dari outlet</div>
                                    </td>
                                    <td class="p-4 text-right text-xs">
                                        <div class="font-mono text-slate-700">Ongkir: Rp {{ number_format($item->delivery_fee, 0, ',', '.') }}</div>
                                        <div class="font-mono text-slate-400">Kemasan: Rp {{ number_format($item->packaging_fee, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900 text-sm">
                                        Rp {{ number_format($item->order?->grand_total, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold
                                            {{ $item->status->value === 'delivered' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $item->status->value === 'on_the_way' ? 'bg-blue-100 text-blue-800 animate-pulse' : '' }}
                                            {{ $item->status->value === 'preparing' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $item->status->value === 'failed' ? 'bg-rose-100 text-rose-800' : '' }}">
                                            {{ $item->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="{{ route('resto.deliveries.show', $item) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-all">
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-10 text-center text-slate-400 text-sm italic">
                                        Belum ada data pesanan delivery/bungkus.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($deliveries->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $deliveries->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
