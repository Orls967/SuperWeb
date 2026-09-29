@extends('layouts.app')

@section('title', 'Admin: Manajemen Pesanan Toko')
@section('subtitle', 'Kelola pengiriman pesanan, nomor resi, dan pembatalan transaksi')

@section('content')
<div class="space-y-6">
    @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Filter bar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-3xl bg-slate-800/60 border border-slate-700/60">
        <div class="flex items-center gap-2 overflow-x-auto text-xs">
            <a href="{{ route('store.admin.orders.index') }}" class="px-3.5 py-1.5 rounded-xl border {{ empty($currentStatus) ? 'bg-amber-500 text-slate-950 font-bold border-amber-500' : 'bg-slate-800 text-slate-400 hover:text-white border-slate-700' }}">
                Semua
            </a>
            @foreach(\Modules\Store\Domain\Enums\OrderStatus::cases() as $st)
            <a href="{{ route('store.admin.orders.index', ['status' => $st->value]) }}" class="px-3.5 py-1.5 rounded-xl border {{ ($currentStatus === $st->value) ? 'bg-amber-500 text-slate-950 font-bold border-amber-500' : 'bg-slate-800 text-slate-400 hover:text-white border-slate-700' }}">
                {{ $st->label() }}
            </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('store.admin.orders.index') }}" class="flex items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $currentSearch }}"
                placeholder="Cari no. order / nama..."
                class="px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs focus:ring-1 focus:ring-amber-500"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold">
                Cari
            </button>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900/80 text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-700/60">
                    <tr>
                        <th class="px-6 py-4">Nomor Order</th>
                        <th class="px-6 py-4">Pelanggan</th>
                        <th class="px-6 py-4">Item & Total</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">No. Resi</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @forelse($orders as $order)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-mono font-bold text-white block">{{ $order->number }}</span>
                            <span class="text-[11px] text-slate-500">{{ $order->created_at->format('d/m/Y H:i') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-bold text-white block">{{ $order->user?->name ?? 'User #' . $order->user_id }}</span>
                            <span class="text-[11px] text-slate-400">{{ $order->user?->email }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-mono font-extrabold text-amber-400 block">{{ $order->formatted_grand_total }}</span>
                            <span class="text-[11px] text-slate-400">{{ $order->items->count() }} jenis item</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $order->status->badgeClasses() }}">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($order->tracking_number)
                                <span class="font-mono text-purple-300">{{ $order->tracking_number }}</span>
                            @else
                                <span class="text-slate-500">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2" x-data="{ shipModal: false }">
                                <a href="{{ route('store.admin.orders.show', $order) }}" class="px-2.5 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs">
                                    Detail
                                </a>

                                @if(in_array($order->status, [\Modules\Store\Domain\Enums\OrderStatus::PAID, \Modules\Store\Domain\Enums\OrderStatus::PROCESSING], true))
                                <button
                                    type="button"
                                    @click="shipModal = true"
                                    class="px-2.5 py-1 bg-purple-500/20 hover:bg-purple-500/30 text-purple-300 border border-purple-500/30 rounded-lg text-xs font-semibold"
                                >
                                    Kirim
                                </button>

                                {{-- Ship Modal --}}
                                <div x-show="shipModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                                    <div @click.away="shipModal = false" class="bg-slate-900 border border-slate-700 rounded-3xl p-6 max-w-sm w-full space-y-4 text-left">
                                        <h4 class="text-base font-bold text-white">Input Nomor Resi Pengiriman</h4>
                                        <p class="text-xs text-slate-400">Order: {{ $order->number }}</p>
                                        <form method="POST" action="{{ route('store.admin.orders.ship', $order) }}" class="space-y-3">
                                            @csrf
                                            <input
                                                type="text"
                                                name="tracking_number"
                                                value="JNE-{{ date('Ymd') }}-{{ rand(1000, 9999) }}"
                                                required
                                                class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono"
                                            >
                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="shipModal = false" class="px-3 py-1.5 rounded-lg text-slate-400 text-xs">Batal</button>
                                                <button type="submit" class="px-4 py-1.5 bg-purple-500 text-white rounded-lg text-xs font-bold">Simpan & Kirim</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endif

                                @if($order->status === \Modules\Store\Domain\Enums\OrderStatus::SHIPPED)
                                <form method="POST" action="{{ route('store.admin.orders.complete', $order) }}" class="inline">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Selesaikan pesanan ini?')" class="px-2.5 py-1 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 rounded-lg text-xs font-semibold">
                                        Selesai
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            Tidak ada pesanan toko dengan kriteria filter saat ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-700/60">
            {{ $orders->links() }}
        </div>
    </div>
</div>
@endsection
