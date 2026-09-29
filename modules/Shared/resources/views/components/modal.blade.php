@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl',
])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div
    x-data="{ show: false, name: '{{ $name }}' }"
    x-show="show"
    x-on:open-modal.window="$event.detail == name ? show = true : null"
    x-on:close-modal.window="$event.detail == name ? show = false : null"
    x-on:keydown.escape.window="show = false"
    style="display: none;"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md"></div>
    </div>

    {{-- Content --}}
    <div
        x-show="show"
        class="bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl transform transition-all sm:w-full {{ $maxWidthClass }} z-10 overflow-hidden"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        @if($title || isset($header))
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-slate-100">
                    {{ $header ?? $title }}
                </h3>
                <button
                    type="button"
                    x-on:click="show = false"
                    class="text-slate-400 hover:text-slate-200 rounded-lg p-1 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        <div class="px-6 py-5">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="px-6 py-4 bg-slate-950/40 border-t border-slate-800 flex justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
