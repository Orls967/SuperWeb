@extends('layouts.app')

@section('title', 'Admin: Manajemen Produk & Stok Toko')
@section('subtitle', 'Publikasi produk, listing unit mobil baru, dan penyesuaian stok inventori')

@section('content')
<div x-data="{ carModal: false, stockModal: false, selectedProduct: null }" class="space-y-6">
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

    {{-- Top Action & Filter Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-4 p-6 rounded-3xl bg-slate-800/60 border border-slate-700/60">
        <div>
            <h3 class="text-base font-bold text-white">Inventori & Katalog Store</h3>
            <p class="text-xs text-slate-400">Total {{ $products->total() }} produk terdaftar dalam database inventori.</p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="carModal = true"
                class="px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-xs flex items-center gap-1.5 transition-all shadow-lg shadow-violet-600/20"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Listing Mobil dari AutoDex
            </button>
        </div>
    </div>

    {{-- Products Table --}}
    <div class="bg-slate-800/60 backdrop-blur-sm rounded-3xl border border-slate-700/60 overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900/80 text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-700/60">
                    <tr>
                        <th class="px-6 py-4">Produk</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Harga OTR / Retail</th>
                        <th class="px-6 py-4 text-center">Stok Fisik</th>
                        <th class="px-6 py-4 text-center">Status Tayang</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40 text-slate-300">
                    @forelse($products as $product)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <img
                                    src="{{ $product->primary_image }}"
                                    alt="{{ $product->name }}"
                                    class="w-12 h-12 rounded-xl object-cover bg-slate-900 border border-slate-700 flex-shrink-0"
                                >
                                <div>
                                    <span class="font-bold text-white block">{{ $product->name }}</span>
                                    <span class="font-mono text-[11px] text-slate-400">SKU: {{ $product->sku }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($product->is_car)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-500/20 text-violet-300 border border-violet-500/30">Mobil</span>
                            @elseif($product->productable_type === 'serve_sparepart')
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">Sparepart</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Aksesoris</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono font-bold text-amber-400">
                            {{ $product->formatted_price }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-mono font-extrabold text-sm {{ $product->cached_stock > 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $product->cached_stock }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <form method="POST" action="{{ route('store.admin.products.toggle', $product) }}" class="inline">
                                @csrf
                                <button
                                    type="submit"
                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold border transition-all {{ $product->is_listed ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20' : 'bg-slate-700/50 text-slate-400 border-slate-600 hover:bg-slate-700' }}"
                                >
                                    {{ $product->is_listed ? 'PUBLISHED' : 'HIDDEN' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                type="button"
                                @click="selectedProduct = {{ json_encode(['id' => $product->id, 'name' => $product->name, 'stock' => $product->cached_stock]) }}; stockModal = true"
                                class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-semibold transition-all"
                            >
                                Sesuaikan Stok
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            Belum ada produk terdaftar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-700/60">
            {{ $products->links() }}
        </div>
    </div>

    {{-- Modal: Listing Mobil dari AutoDex --}}
    <div x-show="carModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div @click.away="carModal = false" class="bg-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6">
            <div>
                <h3 class="text-xl font-bold text-white">Listing Unit Mobil ke Store</h3>
                <p class="text-xs text-slate-400 mt-1">Pilih data mobil dari ensiklopedia AutoDex untuk dijadikan listing produk resmi.</p>
            </div>

            <form method="POST" action="{{ route('store.admin.products.createCarListing') }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Pilih Mobil AutoDex</label>
                    <select name="car_id" required class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs">
                        <option value="">-- Pilih Model Mobil --</option>
                        @foreach($cars as $car)
                            <option value="{{ $car->id }}">{{ $car->brand->name }} {{ $car->model }} ({{ $car->year_start }}) - Rp {{ number_format($car->price_idr, 0, ',', '.') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-300">Harga OTR (IDR)</label>
                        <input type="number" name="price" required min="1000000" step="1000000" placeholder="Contoh: 350000000" class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-300">Jumlah Unit (Stok)</label>
                        <input type="number" name="stock" required min="1" value="1" class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Catatan Listing / Deskripsi</label>
                    <textarea name="description" rows="2" placeholder="Unit baru bergaransi resmi, dokumen lengkap..." class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs resize-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="carModal = false" class="px-4 py-2 rounded-xl text-slate-400 hover:text-white text-xs">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-violet-600 hover:bg-violet-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-violet-600/20">Publikasikan Mobil</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Sesuaikan Stok Inventori --}}
    <div x-show="stockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div @click.away="stockModal = false" class="bg-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 max-w-md w-full space-y-6">
            <div>
                <h3 class="text-xl font-bold text-white">Penyesuaian Stok Inventori</h3>
                <p class="text-xs text-slate-400 mt-1" x-text="selectedProduct ? 'Produk: ' + selectedProduct.name + ' (Stok saat ini: ' + selectedProduct.stock + ')' : ''"></p>
            </div>

            <form
                method="POST"
                :action="'{{ url('admin/store/products') }}/' + (selectedProduct ? selectedProduct.id : '') + '/adjust-stock'"
                class="space-y-4"
            >
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Jumlah Penyesuaian (+ untuk tambah, - untuk kurang)</label>
                    <input type="number" name="qty" required placeholder="Contoh: 10 atau -2" class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-mono">
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Alasan Penyesuaian</label>
                    <select name="reason" required class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs">
                        <option value="purchase">Pembelian / Restok Barang</option>
                        <option value="adjustment">Penyesuaian Opname Manual</option>
                        <option value="return">Pengembalian / Retur</option>
                        <option value="service_usage">Pemakaian Bengkel</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Catatan Tambahan</label>
                    <input type="text" name="note" placeholder="Nomor faktur suplai / alasan opname" class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" @click="stockModal = false" class="px-4 py-2 rounded-xl text-slate-400 hover:text-white text-xs">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-bold shadow-lg shadow-amber-500/20">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
