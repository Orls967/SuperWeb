@foreach($cars as $car)
<div class="glass-card rounded-2xl overflow-hidden group hover:-translate-y-1 transition-all duration-300">
    <!-- Image Placeholder / Logo -->
    <div class="h-40 bg-slate-800/80 relative flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent z-10"></div>
        <span class="text-4xl font-black text-slate-700/50 uppercase tracking-widest z-0 transform -rotate-12">{{ $car->brand->name }}</span>
        
        <!-- Badges -->
        <div class="absolute top-3 left-3 z-20 flex gap-1.5">
            <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $car->brand->category_badge }}">
                {{ $car->brand->category }}
            </span>
        </div>
        <div class="absolute top-3 right-3 z-20 flex gap-1.5">
            <span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $car->fuel_badge }}">
                {{ $car->fuel_type }}
            </span>
        </div>
    </div>
    
    <!-- Content -->
    <div class="p-5 relative z-20 bg-slate-900">
        <div class="mb-3">
            <p class="text-xs font-medium text-slate-400 mb-0.5">{{ $car->brand->name }}</p>
            <h3 class="text-lg font-bold text-white leading-tight truncate">
                <a href="{{ route('autodex.show', $car->slug) }}" class="hover:text-blue-400 transition-colors">
                    {{ $car->model }}
                </a>
            </h3>
            <p class="text-xs text-slate-500 mt-1">{{ $car->year_start }} - {{ $car->year_end ?? 'Present' }}</p>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-4">
            <div class="bg-slate-800/50 rounded-lg p-2 text-center border border-slate-700/30">
                <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">Tenaga</p>
                <p class="text-sm font-bold text-white">{{ $car->horsepower ?? '-' }} <span class="text-[10px] font-normal text-slate-500">HP</span></p>
            </div>
            <div class="bg-slate-800/50 rounded-lg p-2 text-center border border-slate-700/30">
                <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">Torsi</p>
                <p class="text-sm font-bold text-white">{{ $car->torque_nm ?? '-' }} <span class="text-[10px] font-normal text-slate-500">Nm</span></p>
            </div>
        </div>

        <div class="flex items-end justify-between mt-4">
            <div>
                <p class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mb-0.5">Harga Estimasi</p>
                <p class="text-sm font-bold text-emerald-400">{{ $car->formatted_price }}</p>
            </div>
            
            <!-- Quick Actions (Add to Garage/Wishlist via Ajax) -->
            @auth
            <div class="flex gap-1.5" x-data="carCard({{ $car->id }})">
                <!-- Wishlist Btn -->
                <button @click="toggleWishlist()" 
                        class="p-2 rounded-lg border transition-all"
                        :class="inWishlist ? 'bg-pink-500/10 border-pink-500/30 text-pink-500' : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-white hover:border-slate-500'"
                        title="Wishlist">
                    <svg class="w-4 h-4" :fill="inWishlist ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
                
                <!-- Garage Btn -->
                <button @click="toggleGarage()" 
                        class="p-2 rounded-lg border transition-all"
                        :class="inGarage ? 'bg-blue-500/10 border-blue-500/30 text-blue-500' : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-white hover:border-slate-500'"
                        title="My Garage">
                    <svg class="w-4 h-4" :fill="inGarage ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                </button>
            </div>
            @endauth
        </div>
    </div>
</div>
@endforeach

@if($cars->isEmpty())
<div class="col-span-full py-12 text-center glass-card rounded-2xl">
    <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
    <h3 class="text-lg font-bold text-white mb-1">Mobil Tidak Ditemukan</h3>
    <p class="text-slate-400 text-sm">Coba sesuaikan kata kunci pencarian atau filter Anda.</p>
</div>
@endif
