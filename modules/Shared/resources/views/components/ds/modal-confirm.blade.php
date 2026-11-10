@props([
    'id',
    'title' => 'Konfirmasi Aksi',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText' => 'Batal',
    'action' => '',
    'method' => 'POST',
    'variant' => 'danger', // danger, primary
    'idempotencyKey' => null,
])

@php
$key = $idempotencyKey ?? 'IDEMP-'.strtoupper(uniqid());
@endphp

<div
    x-data="{
        open: false,
        submitting: false,
        init() {
            window.addEventListener('open-modal-{{ $id }}', () => {
                this.open = true;
                this.$nextTick(() => { this.$refs.confirmBtn?.focus(); });
            });
        },
        close() {
            this.open = false;
        },
        submitForm() {
            this.submitting = true;
            this.$refs.confirmForm.submit();
        }
    }"
    @keydown.escape.window="close()"
    class="relative z-50"
    aria-labelledby="modal-title-{{ $id }}"
    role="dialog"
    aria-modal="true"
    x-show="open"
    x-cloak
>
    {{-- Overlay Latar Belakang --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
        @click="close()"
    ></div>

    <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20 flex items-center justify-center">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="close()"
            class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-2xl transition-all w-full max-w-lg border border-slate-200 dark:border-slate-700"
        >
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    @if($variant === 'danger')
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    @else
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    @endif
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100" id="modal-title-{{ $id }}">
                        {{ $title }}
                    </h3>
                </div>

                <div class="text-sm text-slate-600 dark:text-slate-300">
                    {{ $slot }}
                </div>
            </div>

            <form x-ref="confirmForm" action="{{ $action }}" method="{{ $method === 'GET' ? 'GET' : 'POST' }}" class="bg-slate-50 dark:bg-slate-900/50 px-6 py-4 flex items-center justify-end gap-3 rounded-b-2xl border-t border-slate-100 dark:border-slate-700">
                @if($method !== 'GET')
                    @csrf
                    @if(!in_array(strtoupper($method), ['POST', 'GET'], true))
                        @method($method)
                    @endif
                    <input type="hidden" name="idempotency_key" value="{{ $key }}" />
                @endif

                <x-ds.button variant="secondary" size="md" @click="close()" :disabled="false">
                    {{ $cancelText }}
                </x-ds.button>

                <x-ds.button
                    :variant="$variant"
                    size="md"
                    type="button"
                    x-ref="confirmBtn"
                    @click="submitForm()"
                    x-bind:disabled="submitting"
                    x-bind:loading="submitting"
                >
                    {{ $confirmText }}
                </x-ds.button>
            </form>
        </div>
    </div>
</div>
