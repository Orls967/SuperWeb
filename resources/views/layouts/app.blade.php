<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="AutoServe - Sistem Manajemen Bengkel Otomotif Terpadu">

        <title>{{ config('app.name', 'AutoServe') }} - @yield('title', 'Dashboard')</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }
            body { font-family: 'Inter', sans-serif; }

            /* Custom scrollbar */
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: #1e293b; }
            ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }

            /* Glassmorphism card */
            .glass-card {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 255, 255, 0.08);
            }

            /* Gradient text */
            .gradient-text {
                background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            /* Pulse animation for status dots */
            .pulse-dot {
                animation: pulse-ring 2s infinite;
            }
            @keyframes pulse-ring {
                0% { box-shadow: 0 0 0 0 rgba(96, 165, 250, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(96, 165, 250, 0); }
                100% { box-shadow: 0 0 0 0 rgba(96, 165, 250, 0); }
            }

            /* Slide in animation */
            .slide-in {
                animation: slideIn 0.3s ease-out;
            }
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body class="antialiased bg-slate-900 text-slate-200">
        <div class="min-h-screen flex" x-data="{ sidebarOpen: true }">

            {{-- ============================================================ --}}
            {{-- SIDEBAR --}}
            {{-- ============================================================ --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-300 bg-slate-800/80 backdrop-blur-xl border-r border-slate-700/50"
                :class="sidebarOpen ? 'w-64' : 'w-20'"
            >
                {{-- Logo --}}
                <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-700/50">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <span class="font-bold text-lg gradient-text whitespace-nowrap" x-show="sidebarOpen" x-cloak x-transition>AutoServe</span>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('dashboard') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Dashboard</span>
                    </a>

                    <a href="{{ route('bookings.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('bookings.create') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Buat Booking</span>
                    </a>

                    @if(auth()->check() && auth()->user()->isStaff())
                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Master Data</p>
                    </div>

                    <a href="{{ route('services.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('services.*') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Jenis Servis</span>
                    </a>

                    <a href="{{ route('spareparts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('spareparts.*') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Sparepart</span>
                    </a>
                    @endif

                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">AutoDex Module</p>
                    </div>

                    <a href="{{ route('autodex.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('autodex.index') || request()->routeIs('autodex.show') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Katalog Mobil</span>
                    </a>

                    <a href="{{ route('autodex.garage.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('autodex.garage.*') ? 'bg-blue-500/20 text-blue-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span x-show="sidebarOpen" x-cloak>My Garage & Wishlist</span>
                    </a>

                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Banking & Finance</p>
                    </div>

                    <a href="{{ route('wallet.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('wallet.index') ? 'bg-indigo-500/20 text-indigo-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Dompet & Saldo</span>
                    </a>

                    <a href="{{ route('wallet.transfer') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('wallet.transfer*') ? 'bg-indigo-500/20 text-indigo-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Transfer Dana</span>
                    </a>

                    <a href="{{ route('wallet.mutasi') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('wallet.mutasi*') ? 'bg-indigo-500/20 text-indigo-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Mutasi Rekening</span>
                    </a>

                    @if(auth()->check() && auth()->user()->isAdmin())
                    <a href="{{ route('admin.ledger.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('admin.ledger.*') ? 'bg-indigo-500/20 text-indigo-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Ledger Pembukuan</span>
                    </a>
                    @endif

                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Store & Merchandise</p>
                    </div>

                    <a href="{{ route('store.catalog.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.catalog.*') || request()->routeIs('store.products.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Katalog Toko</span>
                    </a>

                    <a href="{{ route('store.c2c.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.c2c.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Mobil Bekas (C2C)</span>
                    </a>

                    @auth
                    <a href="{{ route('store.cart.index') }}" class="flex items-center justify-between px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.cart.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <span x-show="sidebarOpen" x-cloak>Keranjang</span>
                        </div>
                        <span x-show="sidebarOpen" x-cloak id="nav-cart-badge" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-300">
                            {{ \Modules\Store\Domain\Models\Cart::where('user_id', auth()->id())->first()?->items_count ?? 0 }}
                        </span>
                    </a>

                    <a href="{{ route('store.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.orders.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Pesanan Saya</span>
                    </a>
                    @endauth

                    @if(auth()->check() && auth()->user()->isAdmin())
                    <a href="{{ route('store.admin.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.admin.orders.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Admin: Pesanan Toko</span>
                    </a>
                    <a href="{{ route('store.admin.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('store.admin.products.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Admin: Produk & Stok</span>
                    </a>
                    @endif

                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Crypto Exchange</p>
                    </div>

                    <a href="{{ route('crypto.market.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('crypto.market.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Pasar Kripto</span>
                    </a>

                    @auth
                    <a href="{{ route('crypto.portfolio.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('crypto.portfolio.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Portofolio Kripto</span>
                    </a>

                    <a href="{{ route('crypto.alerts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('crypto.alerts.*') ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Price Alerts</span>
                    </a>
                    @endauth

                    <div class="pt-4 pb-2 px-3" x-show="sidebarOpen" x-cloak>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">Platform</p>
                    </div>

                    @auth
                    <a href="{{ route('notifications.index') }}" class="flex items-center justify-between px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('notifications.*') ? 'bg-cyan-500/20 text-cyan-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span x-show="sidebarOpen" x-cloak>Notifikasi</span>
                        </div>
                        @php $notifUnread = \Modules\Core\Domain\Models\PlatformNotification::forUser(auth()->id())->unread()->count(); @endphp
                        @if($notifUnread > 0)
                        <span x-show="sidebarOpen" x-cloak class="px-2 py-0.5 text-xs font-semibold rounded-full bg-cyan-500/20 text-cyan-300">{{ $notifUnread }}</span>
                        @endif
                    </a>

                    <a href="{{ route('activity.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ request()->routeIs('activity.*') ? 'bg-cyan-500/20 text-cyan-400' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Activity Feed</span>
                    </a>
                    @endauth
                </nav>

                {{-- Sidebar Toggle --}}
                <div class="px-3 py-4 border-t border-slate-700/50">
                    <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 transition-all">
                        <svg class="w-5 h-5 transition-transform" :class="sidebarOpen ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        <span x-show="sidebarOpen" x-cloak class="text-sm">Tutup</span>
                    </button>
                </div>
            </aside>

            {{-- ============================================================ --}}
            {{-- MAIN CONTENT --}}
            {{-- ============================================================ --}}
            <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
                {{-- Top Bar --}}
                <header class="sticky top-0 z-40 flex items-center justify-between px-6 py-4 bg-slate-900/80 backdrop-blur-lg border-b border-slate-700/50">
                    <div>
                        @if(isset($header))
                            {{ $header }}
                        @else
                            <h1 class="text-xl font-bold text-white">@yield('title', 'Dashboard')</h1>
                            <p class="text-sm text-slate-400">@yield('subtitle', 'Sistem Manajemen Bengkel Otomotif')</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-4" x-data="{ dropdownOpen: false, notifOpen: false, notifCount: {{ $notifUnread ?? 0 }}, notifItems: [] }">
                        @auth
                        {{-- Notification Bell --}}
                        <div class="relative" x-data x-init="fetch('{{ route('notifications.recent') }}').then(r => r.json()).then(d => { notifCount = d.unread_count; notifItems = d.notifications; })">
                            <button @click="notifOpen = !notifOpen" class="relative p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-all border border-transparent hover:border-slate-700/60" title="Notifikasi">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span x-show="notifCount > 0" x-cloak id="topbar-notif-badge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-blue-500 text-white flex items-center justify-center animate-pulse" x-text="notifCount"></span>
                            </button>

                            {{-- Notification Dropdown --}}
                            <div x-show="notifOpen" @click.away="notifOpen = false" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute right-0 mt-2 w-80 rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl z-50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/50">
                                    <span class="text-sm font-semibold text-white">Notifikasi</span>
                                    <button @click="fetch('{{ route('notifications.markAllReadJson') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}}).then(() => { notifCount = 0; notifItems = notifItems.map(n => ({...n, is_read: true})) })" class="text-xs text-blue-400 hover:text-blue-300">Tandai dibaca</button>
                                </div>
                                <div class="max-h-80 overflow-y-auto">
                                    <template x-for="n in notifItems" :key="n.id">
                                        <a :href="n.action_url || '{{ route('notifications.index') }}'" class="flex items-start gap-3 px-4 py-3 border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors" :class="n.is_read ? 'opacity-60' : ''">
                                            <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center" :class="n.icon === 'success' ? 'bg-emerald-500/10' : n.icon === 'warning' ? 'bg-amber-500/10' : n.icon === 'danger' ? 'bg-red-500/10' : 'bg-blue-500/10'">
                                                <svg class="w-4 h-4" :class="n.icon === 'success' ? 'text-emerald-400' : n.icon === 'warning' ? 'text-amber-400' : n.icon === 'danger' ? 'text-red-400' : 'text-blue-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold text-white truncate" x-text="n.title"></p>
                                                <p class="text-xs text-slate-400 line-clamp-2" x-text="n.body"></p>
                                                <span class="text-[10px] text-slate-500" x-text="n.time_ago"></span>
                                            </div>
                                            <span x-show="!n.is_read" class="flex-shrink-0 w-2 h-2 rounded-full bg-blue-400 mt-1"></span>
                                        </a>
                                    </template>
                                    <template x-if="notifItems.length === 0">
                                        <div class="px-4 py-6 text-center text-xs text-slate-500">Tidak ada notifikasi</div>
                                    </template>
                                </div>
                                <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-xs font-medium text-blue-400 hover:bg-slate-700/30 border-t border-slate-700/50">Lihat Semua →</a>
                            </div>
                        </div>

                        <a href="{{ route('store.cart.index') }}" class="relative p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-all border border-transparent hover:border-slate-700/60" title="Keranjang Belanja">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span id="topbar-cart-badge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-amber-500 text-slate-950 flex items-center justify-center">
                                {{ \Modules\Store\Domain\Models\Cart::where('user_id', auth()->id())->first()?->items_count ?? 0 }}
                            </span>
                        </a>

                        <a href="{{ route('wallet.index') }}" class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/80 border border-slate-700/60 hover:border-indigo-500/50 transition-all text-xs font-medium">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-slate-400">Saldo:</span>
                            <span class="font-mono font-bold text-white">{{ auth()->user()->walletBalance('IDR')->format() }}</span>
                        </a>

                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-400 capitalize">{{ auth()->user()->role }}</p>
                        </div>
                        <div class="relative">
                            <button @click="dropdownOpen = !dropdownOpen" class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/25">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </button>
                            <div x-show="dropdownOpen" @click.away="dropdownOpen = false" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute right-0 mt-2 w-48 rounded-xl bg-slate-800 border border-slate-700 shadow-2xl py-1 z-50">
                                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-300 hover:bg-slate-700/50">Profil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-slate-700/50">Logout</button>
                                </form>
                            </div>
                        </div>
                        @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs transition-all">
                            Masuk
                        </a>
                        <a href="{{ route('register') }}" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-semibold text-xs transition-all">
                            Daftar
                        </a>
                        @endauth
                    </div>
                </header>

                {{-- Flash Messages --}}
                @if(session('success'))
                <div class="mx-6 mt-4 slide-in" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition>
                    <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span class="text-sm">{{ session('success') }}</span>
                        <button @click="show = false" class="ml-auto text-emerald-400/60 hover:text-emerald-400">&times;</button>
                    </div>
                </div>
                @endif

                @if(session('error'))
                <div class="mx-6 mt-4 slide-in" x-data="{ show: true }" x-show="show" x-transition>
                    <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span class="text-sm">{{ session('error') }}</span>
                        <button @click="show = false" class="ml-auto text-red-400/60 hover:text-red-400">&times;</button>
                    </div>
                </div>
                @endif

                @if($errors->any())
                <div class="mx-6 mt-4 slide-in">
                    <div class="px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400">
                        <ul class="list-disc list-inside text-sm space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                {{-- Page Content --}}
                <main class="p-6">
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>

        <x-toast />
    </body>
</html>
