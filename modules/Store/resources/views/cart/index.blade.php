@extends('layouts.app')

@section('title', 'Keranjang Belanja')
@section('subtitle', 'Periksa barang pesanan Anda sebelum checkout')

@section('content')
<div class="space-y-8">
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

    @if($cart->items->isEmpty())
    <div class="text-center py-20 bg-slate-800/40 rounded-3xl border border-slate-700/50">
        <div class="w-20 h-20 mx-auto rounded-3xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mb-4">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">Keranjang Belanja Anda Masih Kosong</h3>
        <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">Jelajahi berbagai pilihan mobil, suku cadang asli, dan aksesoris eksklusif di Toko kami.</p>
        <a href="{{ route('store.catalog.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm shadow-xl shadow-amber-500/20 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Mulai Belanja Sekarang
        </a>
    </div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {{-- Items List (8 cols) --}}
        <div class="lg:col-span-8 space-y-4">
            <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden divide-y divide-slate-700/60">
                <div class="px-6 py-4 bg-slate-900/60 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Daftar Item ({{ $cart->items_count }})</span>
                    <a href="{{ route('store.catalog.index') }}" class="text-xs text-amber-400 hover:underline">
                        + Tambah Produk Lain
                    </a>
                </div>

                @foreach($cart->items as $item)
                <div class="p-6 flex flex-col sm:flex-row items-center gap-4 justify-between">
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <img
                            src="{{ $item->product?->primary_image }}"
                            alt="{{ $item->product?->name }}"
                            class="w-20 h-20 rounded-2xl object-cover bg-slate-900 border border-slate-700 flex-shrink-0"
                        >
                        <div>
                            <span class="text-[10px] font-mono text-slate-400 uppercase">SKU: {{ $item->product?->sku }}</span>
                            <h4 class="text-base font-bold text-white">
                                <a href="{{ route('store.products.show', $item->product->slug) }}" class="hover:text-amber-400 transition-colors">
                                    {{ $item->product?->name }}
                                </a>
                            </h4>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ $item->product?->formatted_price }} / unit
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-700/60">
                        {{-- Qty Stepper Form --}}
                        <div class="flex items-center rounded-xl bg-slate-900 border border-slate-700 p-1">
                            <form method="POST" action="{{ route('store.cart.update', $item->product_id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="qty" value="{{ $item->qty - 1 }}">
                                <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                                    -
                                </button>
                            </form>
                            <span class="w-10 text-center font-mono font-bold text-sm text-white">{{ $item->qty }}</span>
                            <form method="POST" action="{{ route('store.cart.update', $item->product_id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="qty" value="{{ $item->qty + 1 }}">
                                <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                                    +
                                </button>
                            </form>
                        </div>

                        {{-- Line Total --}}
                        <div class="text-right">
                            <p class="text-[11px] text-slate-400">Total</p>
                            <p class="text-base font-mono font-bold text-amber-400">
                                Rp {{ number_format($item->line_total, 0, ',', '.') }}
                            </p>
                        </div>

                        {{-- Remove Button --}}
                        <form method="POST" action="{{ route('store.cart.destroy', $item->product_id) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                onclick="return confirm('Hapus item ini dari keranjang?')"
                                class="p-2 text-slate-500 hover:text-red-400 transition-colors"
                                title="Hapus Item"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Order Summary Sidebar (4 cols) --}}
        <div class="lg:col-span-4 bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 space-y-6">
            <h3 class="text-lg font-bold text-white">Ringkasan Pesanan</h3>

            <div class="space-y-3 text-sm divide-y divide-slate-700/40">
                <div class="flex justify-between text-slate-300 pt-2">
                    <span>Total Barang</span>
                    <span class="font-mono text-white">{{ $cart->items_count }} unit</span>
                </div>
                <div class="flex justify-between text-slate-300 pt-3">
                    <span>Total Bobot</span>
                    <span class="font-mono text-white">{{ number_format($cart->total_weight_gram / 1000, 2) }} kg</span>
                </div>
                <div class="flex justify-between text-slate-300 pt-3">
                    <span>Subtotal Produk</span>
                    <span class="font-mono font-bold text-white">Rp {{ number_format($cart->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-900/60 border border-slate-700/60 text-xs text-slate-400 leading-relaxed">
                Biaya pengiriman flat dihitung berdasarkan total berat paket atau penanganan khusus mobil pada langkah berikutnya.
            </div>

            <a
                href="{{ route('store.checkout.index') }}"
                class="block w-full py-4 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-98 text-slate-950 font-extrabold text-center text-sm shadow-xl shadow-amber-500/20 transition-all"
            >
                Lanjut ke Pembayaran &rarr;
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
