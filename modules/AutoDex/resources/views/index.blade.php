@extends('layouts.app')

@section('title', 'AutoDex - Global Car Catalog')
@section('subtitle', 'Jelajahi ensiklopedia mobil global dan tambahkan ke garasi Anda')

@section('content')
<div x-data="autodex()" x-init="initData()">
    
    <!-- Tab Navigation -->
    <div class="flex gap-4 border-b border-slate-700/50 pb-4 mb-6">
        <button @click="activeTab = 'catalog'" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all"
                :class="activeTab === 'catalog' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white'">
            Katalog Mobil
        </button>
        <button @click="activeTab = 'garage'" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all"
                :class="activeTab === 'garage' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'text-slate-400 hover:text-white'">
            My Garage
        </button>
        <button @click="activeTab = 'wishlist'" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-all"
                :class="activeTab === 'wishlist' ? 'bg-pink-500/10 text-pink-400 border border-pink-500/20' : 'text-slate-400 hover:text-white'">
            Wishlist
        </button>
    </div>

    <!-- CATALOG VIEW -->
    <div x-show="activeTab === 'catalog'" x-transition.opacity.duration.300ms>
        <!-- Search & Filter Bar -->
        <div class="glass-card rounded-2xl p-4 mb-6 flex flex-col sm:flex-row gap-4 items-center">
            <div class="relative flex-1 w-full">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" x-model="search" @input.debounce.500ms="fetchCars()" 
                       class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all" 
                       placeholder="Cari merk atau model mobil... (ex: Civic, BYD, Tesla)">
            </div>
            
            <div class="flex gap-3 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
                <select x-model="category" @change="fetchCars()" class="px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-slate-300 text-sm focus:border-blue-500 transition-all min-w-[120px]">
                    <option value="">Semua Market</option>
                    <option value="jdm">JDM (Japan)</option>
                    <option value="usdm">USDM (USA)</option>
                    <option value="euro">EURO (Europe)</option>
                    <option value="korean">Korean</option>
                    <option value="chinese">Chinese</option>
                    <option value="ev">EV Focus</option>
                </select>
                
                <select x-model="fuel" @change="fetchCars()" class="px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-slate-300 text-sm focus:border-blue-500 transition-all min-w-[120px]">
                    <option value="">Semua Mesin</option>
                    <option value="gasoline">Bensin / ICE</option>
                    <option value="diesel">Diesel</option>
                    <option value="hybrid">Hybrid / PHEV</option>
                    <option value="electric">Electric (EV)</option>
                </select>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="flex justify-center items-center py-12">
            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span class="text-slate-400 font-medium">Memuat data mobil...</span>
        </div>

        <!-- Car Grid -->
        <div x-show="!loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="car-grid" x-html="carHtml">
            <!-- Isi dirender via AJAX dari partials/car-list -->
        </div>
        
        <!-- Pagination Container -->
        <div x-show="!loading" class="mt-8 flex justify-center" x-html="paginationHtml"></div>
    </div>
    
    <!-- GARAGE VIEW (PLACEHOLDER) -->
    <div x-show="activeTab === 'garage'" x-cloak class="glass-card rounded-2xl p-8 text-center" x-transition.opacity.duration.300ms>
         <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-500/10 mb-4">
            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
        </div>
        <h2 class="text-xl font-bold text-white mb-2">My Garage</h2>
        <p class="text-slate-400 mb-6 max-w-md mx-auto">Mobil yang Anda tambahkan ke garasi akan muncul di sini. Nantinya Anda bisa memilih mobil dari garasi saat melakukan Booking Servis.</p>
        <a href="{{ route('autodex.garage.index') }}" class="inline-flex px-6 py-2.5 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-medium transition-all shadow-lg shadow-blue-500/25">Kelola Garasi Saya</a>
    </div>

    <!-- WISHLIST VIEW (PLACEHOLDER) -->
    <div x-show="activeTab === 'wishlist'" x-cloak class="glass-card rounded-2xl p-8 text-center" x-transition.opacity.duration.300ms>
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-pink-500/10 mb-4">
            <svg class="w-8 h-8 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
        </div>
        <h2 class="text-xl font-bold text-white mb-2">My Wishlist</h2>
        <p class="text-slate-400 mb-6 max-w-md mx-auto">Daftar mobil impian Anda. Simpan mobil yang Anda inginkan di masa depan.</p>
        <a href="{{ route('autodex.garage.index') }}" class="inline-flex px-6 py-2.5 rounded-xl bg-pink-500 hover:bg-pink-600 text-white font-medium transition-all shadow-lg shadow-pink-500/25">Lihat Wishlist Lengkap</a>
    </div>

</div>

<!-- Alpine JS Logic untuk AutoDex -->
<script>
    document.addEventListener('alpine:init', () => {
        
        // Main AutoDex State
        Alpine.data('autodex', () => ({
            activeTab: 'catalog',
            search: '',
            category: '',
            fuel: '',
            loading: false,
            carHtml: '',
            paginationHtml: '',
            
            initData() {
                this.fetchCars();
            },
            
            async fetchCars(page = 1) {
                this.loading = true;
                try {
                    // Bangun query string
                    const params = new URLSearchParams({
                        search: this.search,
                        category: this.category,
                        fuel: this.fuel,
                        page: page
                    });
                    
                    const response = await fetch(`{{ route('autodex.index') }}?${params.toString()}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });
                    
                    if(response.ok) {
                        const data = await response.json();
                        this.carHtml = data.html;
                        this.paginationHtml = data.pagination;
                        
                        // Re-initialize Alpine on new HTML elements
                        this.$nextTick(() => {
                            window.Alpine.initTree(document.getElementById('car-grid'));
                        });
                    }
                } catch (error) {
                    console.error("Error fetching cars:", error);
                } finally {
                    this.loading = false;
                }
            }
        }));

        // Individual Car Card State
        Alpine.data('carCard', (carId) => ({
            inGarage: false, // Akan lebih baik di-load dari data JSON/HTML attribute di production
            inWishlist: false,
            
            async toggleGarage() {
                try {
                    const res = await fetch(`/autodex/garage/${carId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    
                    if (res.ok) {
                        this.inGarage = data.action === 'added';
                        if(this.inGarage) this.inWishlist = false; // Auto remove from wishlist if added to garage
                        // Tampilkan toast/notifikasi disini jika ada
                    } else {
                        alert(data.message || 'Terjadi kesalahan');
                    }
                } catch (e) { console.error(e); }
            },
            
            async toggleWishlist() {
                try {
                    const res = await fetch(`/autodex/wishlist/${carId}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();
                    
                    if (res.ok) {
                        this.inWishlist = data.action === 'added';
                    } else {
                        alert(data.message || 'Terjadi kesalahan');
                    }
                } catch (e) { console.error(e); }
            }
        }));
    });
</script>
@endsection
