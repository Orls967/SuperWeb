@props([
    'name',
    'id' => null,
    'label' => 'Unggah Berkas',
    'accept' => null,
    'multiple' => false,
    'hint' => 'PDF, PNG, JPG hingga 10MB',
    'required' => false,
])

@php
$uploadId = $id ?? 'upload-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
@endphp

<div class="w-full space-y-1.5">
    <label for="{{ $uploadId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
        {{ $label }}
        @if($required)
            <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
        @endif
    </label>

    <div class="flex justify-center rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 px-6 pt-5 pb-6 bg-slate-50/50 dark:bg-slate-900/30 hover:border-blue-500 transition-colors">
        <div class="space-y-2 text-center">
            <svg class="mx-auto h-10 w-10 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div class="flex text-sm text-slate-600 dark:text-slate-400 justify-center">
                <label for="{{ $uploadId }}" class="relative cursor-pointer rounded-md font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-blue-600">
                    <span>Pilih berkas dari perangkat</span>
                    <input
                        id="{{ $uploadId }}"
                        name="{{ $name }}"
                        type="file"
                        class="sr-only"
                        @if($accept) accept="{{ $accept }}" @endif
                        @if($multiple) multiple @endif
                        @if($required) required aria-required="true" @endif
                    />
                </label>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        </div>
    </div>
</div>

