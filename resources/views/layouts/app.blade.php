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
        <div class="min-h-screen flex" x-data="{
            sidebarOpen: true,
            searchOpen: false,
            searchQuery: '',
            searchResults: [],
            searchLoading: false,
            executeSearch() {
                let url = '{{ route('api.global-search') }}';
                if (this.searchQuery && this.searchQuery.trim().length >= 2) {
                    url += '?q=' + encodeURIComponent(this.searchQuery.trim());
                }
                this.searchLoading = true;
                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        this.searchResults = data.results || [];
                        this.searchLoading = false;
                    })
                    .catch(() => { this.searchLoading = false; });
            }
        }"
        @keydown.window.prevent.cmd.k="searchOpen = true; $nextTick(() => { if($refs.globalSearchInput) { $refs.globalSearchInput.focus(); executeSearch(); } })"
        @keydown.window.prevent.ctrl.k="searchOpen = true; $nextTick(() => { if($refs.globalSearchInput) { $refs.globalSearchInput.focus(); executeSearch(); } })"
        @keydown.window.escape="searchOpen = false"
        >

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
                <nav class="flex-1 px-3 py-4 space-y-3 overflow-y-auto">
                    @php
                        $menuRegistry = app(\Modules\Shared\Application\MenuRegistry::class);
                        $groupedMenu = $menuRegistry->getGroupedItemsForUser(auth()->user());
                        $groupOrder = ['Grup & Admin', 'Otomotif', 'Keuangan', 'Kuliner', 'Properti'];
                        uksort($groupedMenu, function($a, $b) use ($groupOrder) {
                            $idxA = array_search($a, $groupOrder);
                            $idxB = array_search($b, $groupOrder);
                            $idxA = $idxA === false ? 99 : $idxA;
                            $idxB = $idxB === false ? 99 : $idxB;
                            return $idxA <=> $idxB;
                        });
                    @endphp

                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all {{ request()->routeIs('dashboard') ? 'bg-blue-500/20 text-blue-400 font-bold' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <span x-show="sidebarOpen" x-cloak>Dashboard</span>
                    </a>

                    @foreach($groupedMenu as $groupName => $items)
                    <div class="space-y-0.5">
                        <div class="pt-2 pb-1 px-3" x-show="sidebarOpen" x-cloak>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-500">{{ $groupName }}</p>
                        </div>
                        @foreach($items as $item)
                        @php
                            $isActive = $item->isActive();
                        @endphp
                        <a href="{{ route($item->route) }}" class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isActive ? 'bg-indigo-500/20 text-indigo-300 font-semibold' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                {!! $item->icon !!}
                                <span class="truncate" x-show="sidebarOpen" x-cloak>{{ $item->label }}</span>
                            </div>
                            @if($item->badge)
                            <span x-show="sidebarOpen" x-cloak class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-indigo-500/20 text-indigo-300">{{ $item->badge }}</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                    @endforeach
                </nav>

                {{-- Sidebar Toggle --}}
                <div class="px-3 py-3 border-t border-slate-700/50">
                    <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 transition-all">
                        <svg class="w-4 h-4 transition-transform" :class="sidebarOpen ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        <span x-show="sidebarOpen" x-cloak class="text-xs">Tutup</span>
                    </button>
                </div>
            </aside>

            {{-- ============================================================ --}}
            {{-- MAIN CONTENT --}}
            {{-- ============================================================ --}}
            <div class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
                {{-- Top Bar --}}
                <header class="sticky top-0 z-40 flex items-center justify-between px-6 py-4 bg-slate-900/80 backdrop-blur-lg border-b border-slate-700/50">
                    <div class="flex items-center gap-6">
                        <div>
                            @if(isset($header))
                                {{ $header }}
                            @else
                                <h1 class="text-xl font-bold text-white">@yield('title', 'Dashboard')</h1>
                                <p class="text-sm text-slate-400">@yield('subtitle', 'Sistem Manajemen Bengkel Otomotif')</p>
                            @endif
                        </div>

                        {{-- Search Trigger Button --}}
                        <button @click="searchOpen = true; $nextTick(() => { if($refs.globalSearchInput) { $refs.globalSearchInput.focus(); executeSearch(); } })"
                                type="button"
                                class="hidden md:flex items-center gap-3 px-3.5 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-slate-200 transition-all text-xs shadow-inner">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span class="text-slate-400">Cari modul, tenant, menu, tiket...</span>
                            <kbd class="px-1.5 py-0.5 rounded bg-slate-900/80 text-[10px] font-mono text-slate-400 border border-slate-700/80">⌘K</kbd>
                        </button>
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

            {{-- ============================================================ --}}
            {{-- GLOBAL SEARCH MODAL (Ctrl+K) --}}
            {{-- ============================================================ --}}
            <div x-show="searchOpen"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20"
                 role="dialog"
                 aria-modal="true">
                {{-- Backdrop --}}
                <div x-show="searchOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="searchOpen = false"
                     class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity"></div>

                {{-- Modal Dialog --}}
                <div x-show="searchOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="mx-auto max-w-2xl transform divide-y divide-slate-700/60 overflow-hidden rounded-2xl bg-slate-900/95 border border-slate-700/80 shadow-2xl transition-all relative z-10">
                    
                    {{-- Search Header --}}
                    <div class="relative flex items-center px-4">
                        <svg class="pointer-events-none w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               x-ref="globalSearchInput"
                               x-model="searchQuery"
                               @input.debounce.250ms="executeSearch()"
                               placeholder="Cari transaksi, menu resto, tenant mall, tiket parkir, unit..."
                               class="h-14 w-full border-0 bg-transparent pl-3 pr-10 text-white placeholder-slate-400 focus:ring-0 sm:text-sm outline-none" />
                        <div class="absolute right-4 flex items-center gap-2">
                            <div x-show="searchLoading" class="w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                            <kbd class="hidden sm:inline-block px-1.5 py-0.5 rounded bg-slate-800 text-[10px] font-mono text-slate-400 border border-slate-700">ESC</kbd>
                        </div>
                    </div>

                    {{-- Results Container --}}
                    <div class="max-h-96 overflow-y-auto p-2">
                        <template x-if="searchResults.length === 0 && !searchLoading">
                            <div class="py-10 text-center text-sm text-slate-400">
                                <svg class="mx-auto h-8 w-8 text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p>Tidak ada hasil untuk pencarian ini.</p>
                            </div>
                        </template>

                        <template x-for="(item, index) in searchResults" :key="index">
                            <a :href="item.url"
                               class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-800/80 transition-colors group cursor-pointer">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700/60 flex items-center justify-center text-slate-400 group-hover:text-indigo-400 group-hover:border-indigo-500/30 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-200 group-hover:text-white truncate" x-text="item.title"></p>
                                        <p class="text-xs text-slate-400 truncate" x-text="item.subtitle"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md" :class="item.badge_color" x-text="item.badge"></span>
                                    <svg class="w-4 h-4 text-slate-500 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>
                            </a>
                        </template>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-950/50 text-[11px] text-slate-400">
                        <div class="flex items-center gap-3">
                            <span><kbd class="px-1 py-0.5 rounded bg-slate-800 text-[10px] border border-slate-700">↵</kbd> untuk buka</span>
                            <span><kbd class="px-1 py-0.5 rounded bg-slate-800 text-[10px] border border-slate-700">ESC</kbd> untuk keluar</span>
                        </div>
                        <span class="text-slate-500 font-mono text-[10px]">Superwebsite Global Search</span>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <x-toast />
    </body>
</html>
