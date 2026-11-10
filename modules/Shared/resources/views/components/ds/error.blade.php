@props([
    'title' => 'Terjadi Kesalahan',
    'message' => 'Sistem tidak dapat memproses permintaan ini. Silakan coba beberapa saat lagi.',
    'code' => null,
    'retryUrl' => null,
])

<div class="rounded-xl border border-red-200 dark:border-red-900 bg-red-50/70 dark:bg-red-950/30 p-6 text-center space-y-4">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>

    <div>
        <h3 class="text-base font-semibold text-red-900 dark:text-red-200">
            {{ $title }} @if($code)<span class="text-xs font-mono font-normal">({{ $code }})</span>@endif
        </h3>
        <p class="mt-1 text-sm text-red-700 dark:text-red-300 max-w-md mx-auto">
            {{ $message }}
        </p>
    </div>

    @if($retryUrl)
        <div class="pt-2">
            <x-ds.button variant="secondary" size="sm" :href="$retryUrl">
                Muat Ulang Halaman
            </x-ds.button>
        </div>
    @endif
</div>
