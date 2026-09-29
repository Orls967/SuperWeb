@extends('layouts.app')

@section('title', 'Katalog Toko & Showroom')
@section('subtitle', 'Temukan mobil impian, sparepart resmi, dan merchandise otomotif')

@section('content')
<div x-data="catalogApp()" class="space-y-8">
    {{-- Header Banner & Search --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-amber-950/40 p-8 border border-slate-700/50 shadow-2xl">
        <div class="relative z-10 max-w-3xl">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 mb-4">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                AutoServe Official Store
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-3">
                Katalog Produk & Unit Showroom
            </h2>
            <p class="text-slate-300 text-sm sm:text-base mb-6 leading-relaxed">
                Beli mobil bergaransi langsung masuk My Garage, pesan suku cadang orisinal bengkel, atau beli aksesoris eksklusif dengan saldo dompet terpadu Anda.
            </p>

            {{-- Live Search Input --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input
                        type="text"
                        x-model="search"
                        @input.debounce.300ms="updateSearch()"
                        placeholder="Cari mobil, merk, nama suku cadang, atau SKU..."
                        class="w-full pl-10 pr-4 py-3 bg-slate-800/90 border border-slate-700 rounded-2xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 text-sm shadow-inner"
                    >
                </div>

                {{-- Sort Dropdown --}}
                <select
                    x-model="sort"
                    @change="updateSearch()"
                    class="py-3 px-4 bg-slate-800/90 border border-slate-700 rounded-2xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                >
                    <option value="newest">Terbaru</option>
                    <option value="price_asc">Harga Terendah</option>
                    <option value="price_desc">Harga Tertinggi</option>
                    <option value="stock_desc">Stok Terbanyak</option>
                </select>
            </div>
        </div>

        {{-- Subtle background glow --}}
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    {{-- Filter Type Tabs & Category Pills --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        {{-- Type Tabs --}}
        <div class="flex items-center gap-1.5 p-1 bg-slate-800/80 rounded-2xl border border-slate-700/60 text-xs font-semibold">
            <button
                type="button"
                @click="setType('')"
                :class="type === '' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                class="px-4 py-2 rounded-xl transition-all"
            >
                Semua Produk
            </button>
            <button
                type="button"
                @click="setType('cars')"
                :class="type === 'cars' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                class="px-4 py-2 rounded-xl transition-all"
            >
                Mobil
            </button>
            <button
                type="button"
                @click="setType('parts')"
                :class="type === 'parts' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                class="px-4 py-2 rounded-xl transition-all"
            >
                Sparepart
            </button>
            <button
                type="button"
                @click="setType('items')"
                :class="type === 'items' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white'"
                class="px-4 py-2 rounded-xl transition-all"
            >
                Aksesoris & Umum
            </button>
        </div>

        {{-- Categories --}}
        @if($categories->isNotEmpty())
        <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
            <span class="text-xs text-slate-500 whitespace-nowrap">Kategori:</span>
            <button
                type="button"
                @click="setCategory('')"
                :class="category === '' ? 'bg-slate-700 text-white' : 'bg-slate-800/50 text-slate-400 hover:text-white'"
                class="px-3 py-1.5 rounded-xl border border-slate-700/60 text-xs whitespace-nowrap transition-all"
            >
                Semua
            </button>
            @foreach($categories as $cat)
            <button
                type="button"
                @click="setCategory('{{ $cat->slug }}')"
                :class="category === '{{ $cat->slug }}' ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-slate-800/50 text-slate-400 hover:text-white border-slate-700/60'"
                class="px-3 py-1.5 rounded-xl border text-xs whitespace-nowrap transition-all"
            >
                {{ $cat->name }}
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Product Grid --}}
    @if($products->isEmpty())
    <div class="text-center py-16 bg-slate-800/40 rounded-3xl border border-slate-700/50">
        <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <h3 class="text-lg font-bold text-white mb-1">Tidak ada produk ditemukan</h3>
        <p class="text-sm text-slate-400 max-w-md mx-auto">Coba ubah kata kunci pencarian atau bersihkan filter kategori Anda.</p>
        <button type="button" @click="resetFilters()" class="mt-4 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-all">
            Reset Semua Filter
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($products as $product)
        <div class="group relative flex flex-col bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden hover:border-amber-500/40 transition-all duration-300 hover:shadow-xl hover:shadow-amber-500/5">
            {{-- Image & Badge --}}
            <div class="relative h-48 bg-slate-900 overflow-hidden">
                <img
                    src="{{ $product->primary_image }}"
                    alt="{{ $product->name }}"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    loading="lazy"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-transparent to-transparent"></div>

                {{-- Badges --}}
                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                    @if($product->is_car)
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase bg-violet-500/20 text-violet-300 border border-violet-500/30 backdrop-blur-md">
                            Mobil
                        </span>
                    @elseif($product->productable_type === 'serve_sparepart')
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase bg-blue-500/20 text-blue-300 border border-blue-500/30 backdrop-blur-md">
                            Sparepart
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-md">
                            Aksesoris
                        </span>
                    @endif

                    @if($product->category)
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-800/80 text-slate-300 border border-slate-700/80 backdrop-blur-md">
                            {{ $product->category->name }}
                        </span>
                    @endif
                </div>

                {{-- Stock Status --}}
                <div class="absolute bottom-3 right-3">
                    @if($product->cached_stock > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 backdrop-blur-md">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Stok: {{ $product->cached_stock }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-red-500/20 text-red-300 border border-red-500/30 backdrop-blur-md">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                            Habis
                        </span>
                    @endif
                </div>
            </div>

            {{-- Content --}}
            <div class="p-5 flex-1 flex flex-col justify-between">
                <div>
                    <p class="text-[11px] font-mono text-slate-400 mb-1">SKU: {{ $product->sku }}</p>
                    <h3 class="text-base font-bold text-white group-hover:text-amber-400 transition-colors line-clamp-2">
                        <a href="{{ route('store.products.show', $product->slug) }}">
                            {{ $product->name }}
                        </a>
                    </h3>
                    @if($product->description)
                        <p class="text-xs text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">
                            {{ $product->description }}
                        </p>
                    @endif
                </div>

                <div class="mt-5 pt-4 border-t border-slate-700/60 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] text-slate-400">Harga</p>
                        <p class="text-lg font-extrabold text-amber-400 font-mono">
                            {{ $product->formatted_price }}
                        </p>
                    </div>

                    {{-- Add to Cart Button --}}
                    @if($product->cached_stock > 0)
                    <button
                        type="button"
                        @click="addToCart({{ $product->id }})"
                        :disabled="addingId === {{ $product->id }}"
                        class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-950 font-bold text-xs flex items-center gap-1.5 transition-all shadow-lg shadow-amber-500/20 disabled:opacity-50"
                        title="Tambah ke Keranjang"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span x-text="addingId === {{ $product->id }} ? 'Menambahkan...' : 'Beli'"></span>
                    </button>
                    @else
                    <button
                        type="button"
                        disabled
                        class="px-3.5 py-2 rounded-xl bg-slate-700 text-slate-400 font-semibold text-xs cursor-not-allowed"
                    >
                        Habis
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $products->links() }}
    </div>
    @endif
</div>

<script>
function catalogApp() {
    return {
        search: '{{ $currentSearch ?? '' }}',
        category: '{{ $currentCategory ?? '' }}',
        type: '{{ $currentType ?? '' }}',
        sort: '{{ $currentSort ?? 'newest' }}',
        addingId: null,

        updateSearch() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.category) params.set('category', this.category);
            if (this.type) params.set('type', this.type);
            if (this.sort) params.set('sort', this.sort);

            window.location.href = '{{ route('store.catalog.index') }}?' + params.toString();
        },

        setType(val) {
            this.type = val;
            this.updateSearch();
        },

        setCategory(val) {
            this.category = val;
            this.updateSearch();
        },

        resetFilters() {
            this.search = '';
            this.category = '';
            this.type = '';
            this.sort = 'newest';
            window.location.href = '{{ route('store.catalog.index') }}';
        },

        async addToCart(productId) {
            this.addingId = productId;
            try {
                const res = await fetch('{{ route('store.cart.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ product_id: productId, qty: 1 })
                });

                const data = await res.json();
                if (data.success) {
                    // Update header badges
                    const navBadge = document.getElementById('nav-cart-badge');
                    const topBadge = document.getElementById('topbar-cart-badge');
                    const currentCount = parseInt(topBadge ? topBadge.innerText : '0') || 0;
                    if (navBadge) navBadge.innerText = currentCount + 1;
                    if (topBadge) topBadge.innerText = currentCount + 1;

                    alert('Produk berhasil ditambahkan ke keranjang!');
                } else {
                    alert(data.message || 'Gagal menambahkan produk ke keranjang.');
                }
            } catch (err) {
                alert('Terjadi kesalahan saat menambahkan ke keranjang.');
            } finally {
                this.addingId = null;
            }
        }
    }
}
</script>
@endsection
