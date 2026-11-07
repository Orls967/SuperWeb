@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="resaleMarketplaceApp()">
    {{-- Toast Notification --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        Resale Sah Anti-Scalping
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        Batas Plafon 120% & Single Transfer Rule
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-500/10 text-purple-400 border border-purple-500/20">
                        Dynamic Bundling Ready
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Marketplace Tiket Resmi Sekunder
                </h1>
                <p class="text-sm text-slate-400 mt-1 max-w-3xl">
                    Bursa tiket resmi terverifikasi dengan enkripsi rotasi barcode instan, royalti artis otomatis, dan pelindung anti-calo matematis terakreditasi. Dilarang keras transaksi di luar platform untuk menghindari penipuan tiket palsu.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('venue.secondary.sell') }}" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-900 font-bold rounded-xl shadow-lg hover:shadow-amber-500/20 transition-all text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Jual Tiket Saya
                </a>
                <a href="{{ route('venue.secondary.orders') }}" class="inline-flex items-center px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 transition-all text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Riwayat Transaksi
                </a>
                <a href="{{ route('venue.secondary.waitlist') }}" class="inline-flex items-center px-4 py-2.5 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-400 font-semibold rounded-xl border border-cyan-500/30 transition-all text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Antrean Waitlist
                </a>
            </div>
        </div>

        {{-- Anti-Scalping Policy Banner --}}
        <div class="bg-gradient-to-r from-emerald-950/60 via-slate-800/90 to-cyan-950/60 border border-emerald-500/30 rounded-2xl p-5 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20 text-emerald-400 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Jaminan Keamanan Bursa Tiket Resmi (Anti-Scalping Safe Trade)</h3>
                    <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                        Setiap tiket yang dibeli di sini dijamin 100% sah. Sistem kami secara otomatis <strong>memusnahkan barcode lama penjual</strong> dan menerbitkan barcode baru terenkripsi khusus untuk Anda. Batas harga maksimal adalah 120% dari harga asli.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono font-bold text-emerald-400 bg-emerald-900/40 px-3 py-1.5 rounded-lg border border-emerald-500/20 flex-shrink-0">
                <span>ROTASI BARCODE INSTAN</span>
            </div>
        </div>

        {{-- Stat Cards Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg backdrop-blur">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Listing Tiket Aktif</span>
                    <span class="text-emerald-400">● LIVE</span>
                </div>
                <div class="mt-2 text-3xl font-black text-white">
                    {{ number_format($totalListings ?? 0, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-slate-400 flex items-center justify-between">
                    <span>Siap ditransaksikan</span>
                    <span class="text-cyan-400">P2P Terverifikasi</span>
                </div>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg backdrop-blur">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Terselesaikan Hari Ini</span>
                    <span class="text-cyan-400">SETTLED</span>
                </div>
                <div class="mt-2 text-3xl font-black text-cyan-400">
                    {{ number_format($settledToday ?? 0, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-slate-400 flex items-center justify-between">
                    <span>Rotasi barcode sukses</span>
                    <span class="text-emerald-400">100% Kliring</span>
                </div>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg backdrop-blur">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Plafon Harga Legal</span>
                    <span class="text-amber-400">MAX 120%</span>
                </div>
                <div class="mt-2 text-3xl font-black text-amber-400">
                    120.0%
                </div>
                <div class="mt-1 text-xs text-slate-400 flex items-center justify-between">
                    <span>Pencegahan spekulasi calo</span>
                    <span class="text-amber-300">Algoritma V9</span>
                </div>
            </div>

            <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-5 shadow-lg backdrop-blur">
                <div class="flex items-center justify-between text-slate-400 text-xs font-semibold uppercase tracking-wider">
                    <span>Alokasi Pajak PBJT</span>
                    <span class="text-indigo-400">10% RESMI</span>
                </div>
                <div class="mt-2 text-3xl font-black text-indigo-400">
                    10.0%
                </div>
                <div class="mt-1 text-xs text-slate-400 flex items-center justify-between">
                    <span>Pajak hiburan daerah</span>
                    <span class="text-indigo-300">Bapenda Bali</span>
                </div>
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="bg-slate-800/60 border border-slate-700 rounded-2xl p-5 flex flex-col md:flex-row gap-4 items-center justify-between shadow-lg">
            <div class="w-full md:w-96 relative">
                <input type="text" x-model="searchQuery" placeholder="Cari nama event, artis, atau zona..." 
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-all">
                <svg class="w-4 h-4 text-slate-400 absolute right-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <select x-model="selectedZone" class="bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-cyan-500">
                    <option value="">Semua Zona</option>
                    <option value="VIP">VIP Sunset Deck</option>
                    <option value="GENERAL">General Admission</option>
                    <option value="SOFA">Daybed & Sofa</option>
                </select>
                <select x-model="sortOrder" class="bg-slate-900 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-cyan-500">
                    <option value="lowest">Harga Termurah</option>
                    <option value="highest">Harga Tertinggi</option>
                    <option value="newest">Terbaru Terdaftar</option>
                </select>
            </div>
        </div>

        {{-- Listings Grid --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-black text-white flex items-center gap-2">
                    <span>Daftar Tiket Terverifikasi Siap Beli</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-mono">{{ $listings->count() }} Listing</span>
                </h2>
                <span class="text-xs text-slate-400">Diperbarui secara live dengan rotasi hash terproteksi</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($listings as $item)
                    <div class="bg-slate-800 border border-slate-700/80 rounded-2xl overflow-hidden shadow-xl hover:border-cyan-500/50 hover:shadow-cyan-500/10 transition-all flex flex-col justify-between group">
                        {{-- Card Header --}}
                        <div class="p-5 border-b border-slate-700/50 bg-gradient-to-br from-slate-800 to-slate-850">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                        {{ $item->ticket->event->event_code ?? 'EVENT' }}
                                    </span>
                                    <h3 class="text-lg font-bold text-white mt-1.5 line-clamp-1 group-hover:text-cyan-400 transition-colors">
                                        {{ $item->ticket->event->title ?? 'Bali Sunset Festival' }}
                                    </h3>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                    TERVERIFIKASI
                                </span>
                            </div>
                            <div class="mt-3 flex items-center gap-2 text-xs text-slate-400">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                <span>Tiket: #{{ $item->ticket->ticket_number ?? 'TCK-ORIG' }}</span>
                                <span>•</span>
                                <span class="text-amber-400 font-semibold">Transfer 0/1 (Tersedia)</span>
                            </div>
                        </div>

                        {{-- Pricing Breakdown --}}
                        <div class="p-5 bg-slate-900/50 space-y-3">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>Harga Asli Penerbitan:</span>
                                <span class="line-through">Rp {{ number_format($item->original_face_value_idr, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-baseline justify-between">
                                <span class="text-xs text-emerald-400 font-semibold uppercase tracking-wider">Harga Penawaran:</span>
                                <span class="text-2xl font-black text-white">
                                    Rp {{ number_format($item->resale_price_idr, 0, ',', '.') }}
                                </span>
                            </div>

                            {{-- Anti-scalping margin gauge --}}
                            <div class="pt-2">
                                @php
                                    $markupBps = $item->original_face_value_idr > 0 ? (int) (($item->resale_price_idr - $item->original_face_value_idr) * 100 / $item->original_face_value_idr) : 0;
                                @endphp
                                <div class="flex justify-between text-[11px] text-slate-400 mb-1">
                                    <span>Kenaikan Harga: {{ $markupBps }}%</span>
                                    <span class="text-emerald-400 font-medium">Batas Plafon 120% (Aman)</span>
                                </div>
                                <div class="w-full bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-emerald-500 to-amber-500 h-full" style="width: {{ min(100, max(5, ($item->resale_price_idr / ($item->original_face_value_idr * 1.2)) * 100)) }}%"></div>
                                </div>
                            </div>

                            <div class="bg-slate-800/80 rounded-xl p-3 text-[11px] text-slate-400 space-y-1.5 border border-slate-700/50">
                                <div class="flex justify-between">
                                    <span>Pajak Hiburan PBJT (10%):</span>
                                    <span class="text-slate-300 font-medium">Rp {{ number_format((int)($item->resale_price_idr * 0.10), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Platform Escrow Fee (8%):</span>
                                    <span class="text-slate-300 font-medium">Rp {{ number_format((int)($item->resale_price_idr * 0.08), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-indigo-400 font-medium">
                                    <span>Royalti Artis/Promotor (2%):</span>
                                    <span>Rp {{ number_format((int)($item->resale_price_idr * 0.02), 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-emerald-400 font-semibold pt-1 border-t border-slate-700/60">
                                    <span>Penerimaan Bersih Penjual:</span>
                                    <span>Rp {{ number_format((int)($item->resale_price_idr * 0.80), 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Action Button --}}
                        <div class="p-5 border-t border-slate-700/50 bg-slate-800/80">
                            <button type="button" @click="openPurchaseModal({{ $item->toJson() }})" 
                                    class="w-full py-2.5 px-4 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-bold rounded-xl shadow-lg transition-all text-sm flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                Beli & Terbitkan Barcode Baru
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-16 text-center bg-slate-800/40 border border-slate-800 rounded-2xl">
                        <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        <h3 class="text-lg font-bold text-slate-300">Belum Ada Tiket yang Terdaftar di Pasar Sekunder</h3>
                        <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">
                            Seluruh pemegang tiket masih mempertahankan kuotanya. Anda dapat mendaftar ke antrean waitlist untuk mendapatkan penawaran tiket otomatis saat kursi dibatalkan.
                        </p>
                        <a href="{{ route('venue.secondary.waitlist') }}" class="inline-flex items-center px-4 py-2 mt-4 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold rounded-lg">
                            Buka Antrean Waitlist Resmi
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Dynamic Bundles Showcase --}}
        @if(isset($bundles) && count($bundles) > 0)
            <div class="pt-8 border-t border-slate-800">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-black text-white">Paket Dynamic Bundling Resmi</h2>
                        <p class="text-sm text-slate-400">Kombinasi tiket konser + hotel mitra + shuttle transport bergaransi margin.</p>
                    </div>
                    <a href="{{ route('venue.secondary.bundles') }}" class="text-cyan-400 hover:text-cyan-300 text-sm font-semibold">
                        Lihat Seluruh Paket →
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($bundles as $b)
                        <div class="bg-gradient-to-b from-slate-800 to-slate-900 border border-slate-700 rounded-2xl p-6 shadow-lg hover:border-amber-500/40 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        DYNAMIC BUNDLE
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $b->components->count() }} Komponen</span>
                                </div>
                                <h4 class="text-lg font-bold text-white mt-2">{{ $b->package_name }}</h4>
                                <p class="text-xs text-slate-400 mt-1">Acara: {{ $b->event->title ?? 'Event' }}</p>

                                <div class="mt-4 space-y-1.5 bg-slate-950/60 p-3 rounded-xl border border-slate-800 text-xs text-slate-300">
                                    @foreach($b->components as $c)
                                        <div class="flex justify-between items-center text-[11px]">
                                            <span>• {{ $c->name }} ({{ $c->component_type }})</span>
                                            <span class="text-slate-400">Rp {{ number_format($c->base_price_idr, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-700/60 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400">Harga Paket Lengkap:</span>
                                <span class="text-xl font-black text-amber-400">Rp {{ number_format($b->final_price_idr, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Educational Scalper Protection FAQ --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Panduan Keamanan & Aturan Transaksi Pasar Sekunder
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-400 leading-relaxed">
                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-cyan-400 uppercase tracking-wider">Aturan Single Transfer</div>
                    <p>Tiket yang telah dibeli melalui marketplace sekunder terkunci secara permanen dan tidak dapat dipindahtangankan lagi untuk mencegah spekulasi bertingkat.</p>
                </div>
                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-amber-400 uppercase tracking-wider">Batas Plafon Harga 120%</div>
                    <p>Sistem secara otomatis menolak pendaftaran tiket dengan harga lebih dari 120% harga asli penerbitan. Calo yang mencoba menjual di atas plafon akan diblokir.</p>
                </div>
                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-emerald-400 uppercase tracking-wider">Rotasi Barcode Otomatis</div>
                    <p>Seketika pembayaran diselesaikan, barcode lama milik penjual dinyatakan tidak berlaku dan barcode baru berkode hash unik diterbitkan ke pembeli.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Purchase Modal with Waterfall Confirmation --}}
    <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative" @click.away="modalOpen = false">
            <h3 class="text-xl font-bold text-white">Konfirmasi Pembelian Tiket Sekunder</h3>
            <p class="text-xs text-slate-400 mt-1">Penerbitan ulang barcode & rotasi token kriptografis sah.</p>

            <template x-if="activeTicket">
                <div class="mt-4 space-y-4">
                    <div class="bg-slate-800/80 rounded-xl p-4 border border-slate-700 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-400">Kode Listing:</span>
                            <span class="font-mono text-cyan-400" x-text="activeTicket.resale_code"></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-400">Harga Penawaran:</span>
                            <span class="font-bold text-white" x-text="'Rp ' + Number(activeTicket.resale_price_idr).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400 pt-2 border-t border-slate-700">
                            <span>Estimasi Pajak PBJT (10%):</span>
                            <span x-text="'Rp ' + Number(Math.floor(activeTicket.resale_price_idr * 0.10)).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Escrow Fee Platform (8%):</span>
                            <span x-text="'Rp ' + Number(Math.floor(activeTicket.resale_price_idr * 0.08)).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-400">
                            <span>Royalti Artis (2%):</span>
                            <span x-text="'Rp ' + Number(Math.floor(activeTicket.resale_price_idr * 0.02)).toLocaleString('id-ID')"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-300">
                        ⚠️ <strong>Pemberitahuan Single Transfer:</strong> Setelah pembelian ini diselesaikan, tiket ini TIDAK DAPAT dipindahtangankan kembali ke pihak ketiga lainnya. Barcode lama penjual akan segera hangus seketika.
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="modalOpen = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-semibold">
                            Batal
                        </button>
                        <button type="button" @click="confirmPurchase()" class="px-5 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold rounded-lg text-sm shadow-lg">
                            Setujui & Bayar via Escrow
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function resaleMarketplaceApp() {
    return {
        searchQuery: '',
        selectedZone: '',
        sortOrder: 'lowest',
        modalOpen: false,
        activeTicket: null,
        toast: { visible: false, title: '', message: '', type: 'success' },

        openPurchaseModal(ticket) {
            this.activeTicket = ticket;
            this.modalOpen = true;
        },

        confirmPurchase() {
            this.modalOpen = false;
            this.toast = {
                visible: true,
                title: 'Transaksi Escrow Diinisiasi',
                message: 'Silakan selesaikan pembayaran untuk mengunci rotasi barcode tiket sah Anda.',
                type: 'success'
            };
            setTimeout(() => { this.toast.visible = false; }, 4000);
        }
    };
}
</script>
@endsection

{{-- Interactive Seating Map & Authenticity Verification Section --}}
<div class="mt-8 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-700/60 pb-4">
        <div>
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Peta Zona Venue & Distribusi Tiket Sekunder
            </h3>
            <p class="text-xs text-slate-400 mt-1">Pilih zona interaktif di bawah untuk menyaring listing tiket yang tersedia di area spesifik.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Zona Tersedia
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-rose-500/20 text-rose-300 border border-rose-500/30">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span> Zona Penuh (Waitlist)
            </span>
        </div>
    </div>

    {{-- Interactive SVG Venue Zone Map --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-slate-950 p-6 rounded-xl border border-slate-800 flex flex-col items-center justify-center">
            <svg class="w-full max-w-md h-48" viewBox="0 0 500 220" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Stage -->
                <rect x="150" y="10" width="200" height="30" rx="8" fill="#334155" stroke="#64748B" stroke-width="2"/>
                <text x="250" y="30" fill="#E2E8F0" font-size="12" font-weight="bold" text-anchor="middle">MAIN STAGE & DJ BOOTH</text>

                <!-- VIP Sunset Deck -->
                <rect x="40" y="60" width="120" height="80" rx="10" fill="#065F46" stroke="#10B981" stroke-width="2" class="cursor-pointer hover:opacity-80 transition-opacity" @click="selectedZone = 'VIP'"/>
                <text x="100" y="95" fill="#FFFFFF" font-size="11" font-weight="bold" text-anchor="middle">VIP SUNSET DECK</text>
                <text x="100" y="115" fill="#6EE7B7" font-size="10" text-anchor="middle">Listing: 4 Tiket</text>

                <!-- General Admission Pit -->
                <rect x="180" y="60" width="140" height="130" rx="10" fill="#1E293B" stroke="#0EA5E9" stroke-width="2" class="cursor-pointer hover:opacity-80 transition-opacity" @click="selectedZone = 'GENERAL'"/>
                <text x="250" y="120" fill="#FFFFFF" font-size="12" font-weight="bold" text-anchor="middle">GENERAL ADMISSION</text>
                <text x="250" y="140" fill="#38BDF8" font-size="10" text-anchor="middle">Listing: 8 Tiket</text>

                <!-- Daybed & Sofa Lounge -->
                <rect x="340" y="60" width="120" height="80" rx="10" fill="#4C1D95" stroke="#8B5CF6" stroke-width="2" class="cursor-pointer hover:opacity-80 transition-opacity" @click="selectedZone = 'SOFA'"/>
                <text x="400" y="95" fill="#FFFFFF" font-size="11" font-weight="bold" text-anchor="middle">DAYBED & SOFA</text>
                <text x="400" y="115" fill="#C4B5FD" font-size="10" text-anchor="middle">Listing: 2 Tiket</text>

                <!-- Beach Shoreline Bar -->
                <rect x="40" y="160" width="120" height="30" rx="6" fill="#1E293B" stroke="#475569" stroke-width="1.5"/>
                <text x="100" y="180" fill="#94A3B8" font-size="10" text-anchor="middle">Sunset Bar West</text>

                <!-- East Cocktail Deck -->
                <rect x="340" y="160" width="120" height="30" rx="6" fill="#1E293B" stroke="#475569" stroke-width="1.5"/>
                <text x="400" y="180" fill="#94A3B8" font-size="10" text-anchor="middle">East Cocktail Bar</text>
            </svg>
            <div class="mt-3 text-[11px] text-slate-500 text-center">
                Klik salah satu area denah untuk memfilter kartu tiket berdasarkan zona lokasi.
            </div>
        </div>

        <div class="bg-slate-900/80 p-5 rounded-xl border border-slate-700/60 space-y-4 flex flex-col justify-between">
            <div>
                <h4 class="text-sm font-bold text-white mb-2">Cek Keaslian Tiket (Token Hash)</h4>
                <p class="text-xs text-slate-400 mb-3">Masukkan nomor seri tiket atau potongan kode hash untuk memverifikasi keabsahan listing resmi:</p>
                <div class="space-y-2">
                    <input type="text" placeholder="e.g. TCK-ORIG-88001" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-cyan-500">
                    <button type="button" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold rounded-lg">
                        Verifikasi Keabsahan Tiket
                    </button>
                </div>
            </div>
            <div class="p-3 bg-slate-800/80 rounded-lg border border-slate-700/50 text-[11px] text-slate-400 space-y-1">
                <div class="text-emerald-400 font-bold">✓ Proteksi Garansi Bursa 100%</div>
                <p>Tiket palsu atau duplikat secara otomatis ditolak pada mesin scanner gerbang turnstile melalui rotasi kriptografi HMAC.</p>
            </div>
        </div>
    </div>
</div>
