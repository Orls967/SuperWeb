<aside
    class="fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-300 bg-slate-800/90 dark:bg-slate-900/95 backdrop-blur-xl border-r border-slate-700/50"
    :class="sidebarOpen ? 'w-64' : 'w-20'"
>
    {{-- Logo --}}
    <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-700/50">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center shadow-lg shadow-blue-500/25">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
        </div>
        <span class="font-bold text-lg text-white whitespace-nowrap" x-show="sidebarOpen" x-cloak x-transition>AutoServe</span>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-3 py-4 space-y-3 overflow-y-auto">
        @php
            $menuRegistry = app(\Modules\Shared\Application\MenuRegistry::class);
            $groupedMenu = $menuRegistry->getGroupedItemsForUser(auth()->user());
            $groupOrder = ['Grup & Admin', 'Otomotif', 'Keuangan', 'Kuliner', 'Properti', 'Logistik'];
            uksort($groupedMenu, function($a, $b) use ($groupOrder) {
                $idxA = array_search($a, $groupOrder);
                $idxB = array_search($b, $groupOrder);
                $idxA = $idxA === false ? 99 : $idxA;
                $idxB = $idxB === false ? 99 : $idxB;
                return $idxA <=> $idxB;
            });
        @endphp

        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all {{ request()->routeIs('dashboard') ? 'bg-blue-600/20 text-blue-400 font-bold' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
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
            <a href="{{ route($item->route) }}" class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $isActive ? 'bg-blue-600/20 text-blue-300 font-semibold' : 'text-slate-400 hover:bg-slate-700/50 hover:text-slate-200' }}">
                <div class="flex items-center gap-2.5 min-w-0">
                    {!! $item->icon !!}
                    <span class="truncate" x-show="sidebarOpen" x-cloak>{{ $item->label }}</span>
                </div>
                @if($item->badge)
                <span x-show="sidebarOpen" x-cloak class="px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-blue-500/20 text-blue-300">{{ $item->badge }}</span>
                @endif
            </a>
            @endforeach
        </div>
        @endforeach
    </nav>

    {{-- Sidebar Toggle --}}
    <div class="px-3 py-3 border-t border-slate-700/50">
        <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-slate-400 hover:bg-slate-700/50 hover:text-slate-200 transition-all" aria-label="Alihkan Sidebar">
            <svg class="w-4 h-4 transition-transform" :class="sidebarOpen ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            <span x-show="sidebarOpen" x-cloak class="text-xs">Tutup</span>
        </button>
    </div>
</aside>

