@extends('layouts.app')

@section('title', 'Riwayat Pesanan Saya')
@section('subtitle', 'Daftar transaksi belanja produk dan unit mobil Anda')

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

    @if($orders->isEmpty())
    <div class="text-center py-20 bg-slate-800/40 rounded-3xl border border-slate-700/50">
        <div class="w-16 h-16 mx-auto rounded-3xl bg-slate-700/40 flex items-center justify-center text-slate-400 mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <h3 class="text-lg font-bold text-white mb-1">Belum Ada Pesanan</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto mb-6">Anda belum pernah melakukan pemesanan di AutoServe Official Store.</p>
        <a href="{{ route('store.catalog.index') }}" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 transition-all">
            Mulai Belanja &rarr;
        </a>
    </div>
    @else
    <div class="space-y-4">
        @foreach($orders as $order)
        <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 hover:border-slate-600 transition-all space-y-4">
            {{-- Header info --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-700/60">
                <div class="flex items-center gap-3">
                    <span class="font-mono font-bold text-base text-white">{{ $order->number }}</span>
                    <span class="text-xs text-slate-400">{{ $order->created_at->format('d M Y, H:i') }}</span>
                </div>

                {{-- Status Badge --}}
                <span class="px-3 py-1 rounded-full text-xs font-semibold border {{ $order->status->badgeClasses() }}">
                    {{ $order->status->label() }}
                </span>
            </div>

            {{-- Items Preview --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($order->items as $item)
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-900/60 border border-slate-700/40">
                    <img
                        src="{{ $item->product?->primary_image }}"
                        alt="{{ $item->name_snapshot }}"
                        class="w-12 h-12 rounded-xl object-cover bg-slate-800 flex-shrink-0"
                    >
                    <div class="truncate">
                        <span class="text-xs font-bold text-white block truncate">{{ $item->name_snapshot }}</span>
                        <span class="text-[11px] text-slate-400">{{ $item->qty }} item &bull; Rp {{ number_format($item->price_snapshot, 0, ',', '.') }}</span>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Footer info & Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-700/60">
                <div>
                    <span class="text-xs text-slate-400">Total Pembayaran:</span>
                    <span class="font-mono font-extrabold text-lg text-amber-400 ml-1.5">{{ $order->formatted_grand_total }}</span>
                </div>

                <div class="flex items-center gap-2">
                    @if($order->status === \Modules\Store\Domain\Enums\OrderStatus::SHIPPED)
                    <form method="POST" action="{{ route('store.orders.confirm', $order) }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            onclick="return confirm('Konfirmasi bahwa barang pesanan telah Anda terima dengan baik?')"
                            class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition-all shadow-md shadow-emerald-500/20"
                        >
                            Konfirmasi Terima
                        </button>
                    </form>
                    @endif

                    <a
                        href="{{ route('store.orders.show', $order) }}"
                        class="px-4 py-2 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-semibold text-xs transition-all"
                    >
                        Detail Pesanan &rarr;
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $orders->links() }}
    </div>
    @endif
</div>
@endsection
