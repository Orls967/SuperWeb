<header class="sticky top-0 z-40 flex items-center justify-between px-6 py-4 bg-slate-900/90 backdrop-blur-lg border-b border-slate-700/50">
    <div class="flex items-center gap-6">
        <div>
            @if(isset($header))
                {{ $header }}
            @else
                <h1 class="text-xl font-bold text-white">@yield('title', 'Dashboard')</h1>
                <p class="text-sm text-slate-400">@yield('subtitle', 'Sistem Manajemen Terpadu')</p>
            @endif
        </div>

        {{-- Search Trigger Button --}}
        <button @click="$dispatch('open-command-palette')"
                type="button"
                aria-label="Buka Pencarian Global"
                class="hidden md:flex items-center gap-3 px-3.5 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700/60 text-slate-400 hover:text-slate-200 transition-all text-xs shadow-inner">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span class="text-slate-400">Cari 46+ modul, 60 peran RBAC, audit...</span>
            <kbd class="px-1.5 py-0.5 rounded bg-slate-900/80 text-[10px] font-mono text-slate-400 border border-slate-700/80">⌘K</kbd>
        </button>
    </div>

    <div class="flex items-center gap-4" x-data="{ dropdownOpen: false, notifOpen: false, notifCount: 0, notifItems: [] }">
        {{-- Theme Switcher di Topbar --}}
        <x-ds.theme-switch />

        @auth
        {{-- Notification Bell --}}
        <div class="relative" x-data x-init="fetch('{{ route('notifications.recent') }}').then(r => r.json()).then(d => { notifCount = d.unread_count || 0; notifItems = d.notifications || []; }).catch(() => {})">
            <button @click="notifOpen = !notifOpen" class="relative p-2 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition-all border border-transparent hover:border-slate-700/60" aria-label="Notifikasi">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span x-show="notifCount > 0" x-cloak id="topbar-notif-badge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-blue-500 text-white flex items-center justify-center animate-pulse" x-text="notifCount"></span>
            </button>

            {{-- Notification Dropdown --}}
            <div x-show="notifOpen" @click.away="notifOpen = false" x-cloak
                 class="absolute right-0 mt-2 w-80 rounded-2xl bg-slate-800 border border-slate-700 shadow-2xl z-50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700/50">
                    <span class="text-sm font-semibold text-white">Notifikasi</span>
                    <button @click="fetch('{{ route('notifications.markAllReadJson') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json'}}).then(() => { notifCount = 0; }).catch(() => {})" class="text-xs text-blue-400 hover:text-blue-300">Tandai dibaca</button>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <template x-for="n in notifItems" :key="n.id">
                        <a :href="n.action_url || '{{ route('notifications.index') }}'" class="flex items-start gap-3 px-4 py-3 border-b border-slate-700/30 hover:bg-slate-700/30 transition-colors" :class="n.is_read ? 'opacity-60' : ''">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center bg-blue-500/10 text-blue-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white truncate" x-text="n.title"></p>
                                <p class="text-xs text-slate-400 line-clamp-2" x-text="n.body"></p>
                            </div>
                        </a>
                    </template>
                </div>
                <a href="{{ route('notifications.index') }}" class="block px-4 py-2.5 text-center text-xs font-medium text-blue-400 hover:bg-slate-700/30 border-t border-slate-700/50">Lihat Semua →</a>
            </div>
        </div>

        <div class="text-right hidden sm:block">
            <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-400 capitalize">{{ auth()->user()->role ?? 'User' }}</p>
        </div>
        <div class="relative">
            <button @click="dropdownOpen = !dropdownOpen" aria-label="Menu Pengguna" class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-500/25">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </button>
            <div x-show="dropdownOpen" @click.away="dropdownOpen = false" x-cloak
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
