<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Pesanan Katering & Nasi Kotak Massal
                </h2>
                <p class="text-sm text-slate-500 mt-1">Kelola pesanan katering skala besar dengan sistem pembayaran bertahap (Deposit 30% & Pelunasan 70%)</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('resto.catering.create') }}" class="px-5 py-2.5 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-500/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Buat Pesanan Katering Baru</span>
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
                <form method="GET" action="{{ route('resto.catering.index') }}" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div>
                        <select name="outlet_id" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Semua Outlet</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}" {{ $selectedOutletId === $o->id ? 'selected' : '' }}>{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-200 text-xs font-medium focus:ring-amber-500 focus:border-amber-500">
                            <option value="">Semua Status</option>
                            <option value="quoted" {{ $selectedStatus === 'quoted' ? 'selected' : '' }}>Penawaran (Quoted)</option>
                            <option value="confirmed" {{ $selectedStatus === 'confirmed' ? 'selected' : '' }}>Terkonfirmasi (Deposit Ditahan)</option>
                            <option value="cooking" {{ $selectedStatus === 'cooking' ? 'selected' : '' }}>Dalam Proses Dapur</option>
                            <option value="delivered" {{ $selectedStatus === 'delivered' ? 'selected' : '' }}>Terkirim</option>
                            <option value="completed" {{ $selectedStatus === 'completed' ? 'selected' : '' }}>Selesai & Lunas</option>
                            <option value="cancelled" {{ $selectedStatus === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
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
                                <th class="p-4">No. Katering & Acara</th>
                                <th class="p-4">Pelanggan & Kontak</th>
                                <th class="p-4">Outlet & Paket</th>
                                <th class="p-4 text-center">Pax</th>
                                <th class="p-4 text-right">Deposit 30%</th>
                                <th class="p-4 text-right">Total Tagihan</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($orders as $order)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="p-4">
                                        <div class="font-mono font-bold text-amber-700">#{{ $order->number }}</div>
                                        <div class="text-[11px] text-slate-500 font-semibold mt-0.5">Tgl Acara: {{ $order->event_date->format('d/m/Y') }} ({{ substr($order->event_time, 0, 5) }} WIB)</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-800">{{ $order->customer_name }}</div>
                                        <div class="text-xs text-slate-500 font-mono">{{ $order->customer_phone }}</div>
                                        <div class="text-xs text-slate-400 truncate max-w-xs mt-0.5">{{ $order->delivery_address }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-semibold text-slate-800">{{ $order->outlet?->name }}</div>
                                        <div class="text-xs text-amber-600 font-medium">{{ $order->package?->name ?: 'Paket Reguler Padang' }}</div>
                                    </td>
                                    <td class="p-4 text-center font-bold text-slate-800 text-sm">
                                        {{ $order->pax }} pax
                                    </td>
                                    <td class="p-4 text-right font-mono text-xs text-slate-700">
                                        Rp {{ number_format($order->deposit_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-right font-black text-slate-900 text-sm">
                                        Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold
                                            {{ $order->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $order->status->value === 'confirmed' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $order->status->value === 'cooking' ? 'bg-purple-100 text-purple-800 animate-pulse' : '' }}
                                            {{ $order->status->value === 'quoted' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $order->status->value === 'cancelled' ? 'bg-rose-100 text-rose-800' : '' }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <a href="{{ route('resto.catering.show', $order) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-all">
                                            Rincian &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-10 text-center text-slate-400 text-sm italic">
                                        Belum ada pesanan katering tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
