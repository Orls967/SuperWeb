@props([
    'items' => [
        [
            'category' => 'ACCOMMODATION',
            'title' => 'Malam Gratis Kamar Deluxe',
            'points' => 15000,
            'line' => 'HTL_ROOM',
            'badge' => 'Paling Populer',
        ],
        [
            'category' => 'ENTERTAINMENT',
            'title' => 'Tiket Masuk Konser Sunset Festival',
            'points' => 12000,
            'line' => 'VEN_TICKET',
            'badge' => 'Eksklusif Venue',
        ],
        [
            'category' => 'DINING',
            'title' => 'Voucher Santap Fine Dining Rp 500.000',
            'points' => 5000,
            'line' => 'HTL_FNB',
            'badge' => 'Instan',
        ],
        [
            'category' => 'WELLNESS',
            'title' => 'Paket Spa Tradisional 90 Menit',
            'points' => 4500,
            'line' => 'SPA_TREAT',
            'badge' => 'Relaksasi',
        ],
        [
            'category' => 'TRANSPORT',
            'title' => 'Antar-Jemput Bandara Limusin Mewah',
            'points' => 3500,
            'line' => 'MOB_TRANS',
            'badge' => 'VIP',
        ],
        [
            'category' => 'GREEN',
            'title' => 'Adopsi Terumbu Karang 1 Fragmen Karang',
            'points' => 1000,
            'line' => 'ESG_DONAT',
            'badge' => 'Donasi ESG',
        ],
    ],
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm"
     x-data="{ selectedCategory: 'ALL' }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-3 mb-5">
        <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>Katalog Matriks Penukaran Hadiah (Redemption Matrix)</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Koleksi Penebusan Poin di 17 Lini Ekosistem Tanpa Blackout Date
            </p>
        </div>

        <div class="flex items-center space-x-1.5 text-xs font-semibold overflow-x-auto pb-1">
            <button type="button" @click="selectedCategory = 'ALL'"
                    :class="selectedCategory === 'ALL' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                    class="px-3 py-1.5 rounded-lg transition">
                Semua
            </button>
            <button type="button" @click="selectedCategory = 'ACCOMMODATION'"
                    :class="selectedCategory === 'ACCOMMODATION' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                    class="px-3 py-1.5 rounded-lg transition">
                Kamar
            </button>
            <button type="button" @click="selectedCategory = 'ENTERTAINMENT'"
                    :class="selectedCategory === 'ENTERTAINMENT' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                    class="px-3 py-1.5 rounded-lg transition">
                Festival
            </button>
            <button type="button" @click="selectedCategory = 'DINING'"
                    :class="selectedCategory === 'DINING' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                    class="px-3 py-1.5 rounded-lg transition">
                Dining
            </button>
            <button type="button" @click="selectedCategory = 'GREEN'"
                    :class="selectedCategory === 'GREEN' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                    class="px-3 py-1.5 rounded-lg transition">
                ESG
            </button>
        </div>
    </div>

    <!-- Items Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($items as $item)
            <div class="p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex flex-col justify-between hover:shadow-md transition text-xs"
                 x-show="selectedCategory === 'ALL' || selectedCategory === '{{ $item['category'] }}'"
                 x-transition>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                            {{ $item['badge'] }}
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ $item['line'] }}</span>
                    </div>
                    <h4 class="font-bold text-slate-900 dark:text-white text-sm mb-1">{{ $item['title'] }}</h4>
                </div>

                <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between mt-3">
                    <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm font-mono">
                        {{ number_format($item['points']) }} PTS
                    </span>
                    <button type="button" class="px-3 py-1 bg-slate-900 dark:bg-slate-100 hover:bg-indigo-600 dark:hover:bg-indigo-600 text-white dark:text-slate-900 dark:hover:text-white font-bold rounded-lg transition text-xs">
                        Tebus
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

