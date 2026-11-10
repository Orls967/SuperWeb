@props([
    'type' => 'success', // success, error, warning, info
    'title' => null,
    'message' => null,
    'duration' => 4000,
])

@php
$colorClasses = match($type) {
    'error' => 'bg-red-50 dark:bg-red-950/80 border-red-200 dark:border-red-800 text-red-900 dark:text-red-200',
    'warning' => 'bg-amber-50 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200',
    'info' => 'bg-sky-50 dark:bg-sky-950/80 border-sky-200 dark:border-sky-800 text-sky-900 dark:text-sky-200',
    default => 'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200',
};

$iconColor = match($type) {
    'error' => 'text-red-600 dark:text-red-400',
    'warning' => 'text-amber-600 dark:text-amber-400',
    'info' => 'text-sky-600 dark:text-sky-400',
    default => 'text-emerald-600 dark:text-emerald-400',
};
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-init="setTimeout(() => show = false, {{ $duration }})"
    x-transition:enter="transform ease-out duration-300 transition"
    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    role="alert"
    aria-live="polite"
    class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border p-4 shadow-lg {{ $colorClasses }}"
>
    <div class="flex items-start gap-3">
        <div class="shrink-0 {{ $iconColor }}">
            @if($type === 'error')
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            @elseif($type === 'warning')
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            @else
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            @endif
        </div>

        <div class="w-0 flex-1 pt-0.5">
            @if($title)
                <p class="text-sm font-semibold">{{ $title }}</p>
            @endif
            @if($message || $slot->isNotEmpty())
                <p class="text-xs mt-0.5 opacity-90">{{ $message ?? $slot }}</p>
            @endif
        </div>

        <div class="ml-4 flex shrink-0">
            <button
                type="button"
                @click="show = false"
                class="inline-flex rounded-md p-1.5 opacity-60 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                aria-label="Tutup notifikasi"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</div>

