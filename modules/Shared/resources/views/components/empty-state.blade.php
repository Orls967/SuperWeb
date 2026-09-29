@props([
    'title' => 'Belum ada data',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center p-8 sm:p-12 rounded-2xl border border-dashed border-slate-700/60 bg-slate-800/30']) }}>
    <div class="w-14 h-14 rounded-2xl bg-slate-800 border border-slate-700/50 flex items-center justify-center text-slate-400 mb-4 shadow-inner">
        @if($icon)
            {{ $icon }}
        @else
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        @endif
    </div>

    <h4 class="text-base font-semibold text-slate-200">{{ $title }}</h4>

    @if($description)
        <p class="text-sm text-slate-400 mt-1 max-w-sm">{{ $description }}</p>
    @endif

    @if($slot->isNotEmpty())
        <div class="mt-5 flex items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
