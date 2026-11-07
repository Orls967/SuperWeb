@props([
    'title' => 'Tidak Ada Data Ditemukan',
    'description' => 'Belum ada catatan transaksi pada kategori ini.',
    'actionLabel' => null,
    'actionUrl' => null,
    'icon' => 'inbox',
])

<div class="py-16 px-6 text-center bg-slate-800/40 border border-slate-800 rounded-2xl space-y-4">
    <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 border border-slate-700/60 flex items-center justify-center text-slate-500">
        @if($icon === 'ticket')
            <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        @elseif($icon === 'clock')
            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @else
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
        @endif
    </div>

    <div class="space-y-1">
        <h4 class="text-base font-bold text-white">{{ $title }}</h4>
        <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">{{ $description }}</p>
    </div>

    @if($actionLabel && $actionUrl)
        <div class="pt-2">
            <a href="{{ $actionUrl }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">
                {{ $actionLabel }}
            </a>
        </div>
    @endif
</div>

