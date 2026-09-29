@extends('layouts.app')

@section('title', $product->name)
@section('subtitle', 'Detail Produk Store')

@section('content')
<div x-data="productDetailApp()" class="space-y-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('store.catalog.index') }}" class="hover:text-amber-400 transition-colors">Toko</a>
        <span>/</span>
        @if($product->category)
            <a href="{{ route('store.catalog.index', ['category' => $product->category->slug]) }}" class="hover:text-amber-400 transition-colors">
                {{ $product->category->name }}
            </a>
            <span>/</span>
        @endif
        <span class="text-slate-200 truncate max-w-xs">{{ $product->name }}</span>
    </nav>

    {{-- Main Product Card --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 p-6 lg:p-8">
        {{-- Product Image Gallery (5 cols) --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="relative aspect-video sm:aspect-square rounded-2xl bg-slate-900 overflow-hidden border border-slate-700/60 shadow-xl">
                <img
                    :src="activeImage"
                    alt="{{ $product->name }}"
                    class="w-full h-full object-cover transition-all duration-300"
                >
                @if($product->is_car)
                    <span class="absolute top-3 left-3 px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-violet-500/20 text-violet-300 border border-violet-500/30 backdrop-blur-md">
                        Unit Showroom Mobil
                    </span>
                @endif
            </div>

            {{-- Image Thumbnails --}}
            @if(!empty($product->images) && count($product->images) > 1)
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach($product->images as $img)
                <button
                    type="button"
                    @click="activeImage = '{{ $img }}'"
                    :class="activeImage === '{{ $img }}' ? 'ring-2 ring-amber-500' : 'opacity-70 hover:opacity-100'"
                    class="w-16 h-16 rounded-xl overflow-hidden bg-slate-900 border border-slate-700 flex-shrink-0 transition-all"
                >
                    <img src="{{ $img }}" class="w-full h-full object-cover">
                </button>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Product Info & Purchase (7 cols) --}}
        <div class="lg:col-span-7 flex flex-col justify-between space-y-6">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono text-xs text-slate-400">SKU: {{ $product->sku }}</span>
                    @if($product->cached_stock > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Stok Tersedia: {{ $product->cached_stock }} unit
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
                            <span class="w-2 h-2 rounded-full bg-red-400"></span>
                            Stok Habis
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    {{ $product->name }}
                </h1>

                {{-- Price Display --}}
                <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/60 flex items-baseline gap-3">
                    <span class="text-3xl font-extrabold text-amber-400 font-mono">
                        {{ $product->formatted_price }}
                    </span>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                        <span class="text-sm font-mono text-slate-500 line-through">
                            Rp {{ number_format($product->compare_at_price, 0, ',', '.') }}
                        </span>
                    @endif
                </div>

                {{-- Car Special Callout --}}
                @if($product->is_car)
                <div class="p-4 rounded-2xl bg-gradient-to-r from-violet-500/10 to-indigo-500/10 border border-violet-500/20 text-xs text-violet-300 flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-violet-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <strong class="font-bold text-white block mb-0.5">Integrasi My Garage Otomatis:</strong>
                        Saat pesanan diselesaikan, mobil ini akan otomatis terdaftar sebagai kendaraan resmi ber-STNK di <em>My Garage</em> Anda dan dihapus dari daftar <em>Wishlist</em>.
                    </div>
                </div>
                @endif

                {{-- Description --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Deskripsi Produk</h3>
                    <div class="text-sm text-slate-300 leading-relaxed whitespace-pre-line bg-slate-900/40 p-4 rounded-2xl border border-slate-700/40">
                        {{ $product->description ?: 'Tidak ada deskripsi tambahan untuk produk ini.' }}
                    </div>
                </div>

                {{-- Meta details --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-700/40">
                        <span class="text-slate-400 block">Kategori</span>
                        <span class="font-semibold text-white">{{ $product->category?->name ?? 'Umum' }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-700/40">
                        <span class="text-slate-400 block">Berat Pengiriman</span>
                        <span class="font-semibold text-white">{{ number_format($product->weight_gram / 1000, 2) }} kg</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-700/40">
                        <span class="text-slate-400 block">Tipe Barang</span>
                        <span class="font-semibold text-white">{{ $product->is_car ? 'Kendaraan' : ($product->productable_type === 'serve_sparepart' ? 'Sparepart Bengkel' : 'Merchandise') }}</span>
                    </div>
                </div>
            </div>

            {{-- Purchase Actions --}}
            <div class="pt-6 border-t border-slate-700/60 space-y-4">
                @if($product->cached_stock > 0)
                <div class="flex flex-col sm:flex-row items-center gap-4">
                    {{-- Qty Stepper --}}
                    <div class="flex items-center rounded-2xl bg-slate-900 border border-slate-700 p-1">
                        <button
                            type="button"
                            @click="decrementQty()"
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                        </button>
                        <input
                            type="number"
                            x-model.number="qty"
                            min="1"
                            max="{{ $product->cached_stock }}"
                            class="w-14 text-center bg-transparent border-0 text-white font-mono font-bold text-sm focus:ring-0"
                            readonly
                        >
                        <button
                            type="button"
                            @click="incrementQty({{ $product->cached_stock }})"
                            class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>

                    {{-- Add to Cart --}}
                    <button
                        type="button"
                        @click="addToCart()"
                        :disabled="loading"
                        class="flex-1 w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-98 text-slate-950 font-extrabold text-sm flex items-center justify-center gap-2 transition-all shadow-xl shadow-amber-500/20 disabled:opacity-50"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span x-text="loading ? 'Memproses...' : 'Tambah ke Keranjang'"></span>
                    </button>
                </div>
                @else
                <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/60 text-center text-slate-400 text-sm">
                    Mohon maaf, saat ini stok produk ini sedang habis. Silakan periksa kembali nanti.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Related Products --}}
    @if($relatedProducts->isNotEmpty())
    <div class="space-y-4 pt-6">
        <h3 class="text-xl font-bold text-white">Produk Terkait Lainnya</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($relatedProducts as $rel)
            <div class="bg-slate-800/60 rounded-2xl border border-slate-700/60 overflow-hidden hover:border-amber-500/40 transition-all flex flex-col justify-between p-4">
                <img src="{{ $rel->primary_image }}" alt="{{ $rel->name }}" class="w-full h-36 object-cover rounded-xl mb-3">
                <div>
                    <h4 class="text-sm font-bold text-white truncate">
                        <a href="{{ route('store.products.show', $rel->slug) }}" class="hover:text-amber-400">
                            {{ $rel->name }}
                        </a>
                    </h4>
                    <p class="text-amber-400 font-mono font-bold text-sm mt-1">{{ $rel->formatted_price }}</p>
                </div>
                <a href="{{ route('store.products.show', $rel->slug) }}" class="mt-3 block text-center py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-all">
                    Lihat Produk
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<script>
function productDetailApp() {
    return {
        activeImage: '{{ $product->primary_image }}',
        qty: 1,
        loading: false,

        incrementQty(max) {
            if (this.qty < max) this.qty++;
        },

        decrementQty() {
            if (this.qty > 1) this.qty--;
        },

        async addToCart() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('store.cart.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ product_id: {{ $product->id }}, qty: this.qty })
                });

                const data = await res.json();
                if (data.success) {
                    const topBadge = document.getElementById('topbar-cart-badge');
                    const navBadge = document.getElementById('nav-cart-badge');
                    const count = (parseInt(topBadge ? topBadge.innerText : '0') || 0) + this.qty;
                    if (topBadge) topBadge.innerText = count;
                    if (navBadge) navBadge.innerText = count;

                    if (confirm('Produk berhasil ditambahkan ke keranjang! Apakah Anda ingin langsung melihat Keranjang?')) {
                        window.location.href = '{{ route('store.cart.index') }}';
                    }
                } else {
                    alert(data.message || 'Gagal menambahkan produk ke keranjang.');
                }
            } catch (err) {
                alert('Terjadi kesalahan saat memproses.');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection
