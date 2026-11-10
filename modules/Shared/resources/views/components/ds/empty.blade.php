@props([
    'title' => 'Belum ada data',
    'description' => 'Data yang Anda cari tidak ditemukan atau belum pernah dibuat.',
    'icon' => 'archive',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700 p-8 text-center bg-white/50 dark:bg-slate-800/50']) }}>
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-300">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
    </div>

    <h3 class="mt-4 text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h3>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">{{ $description }}</p>

    @if(isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
