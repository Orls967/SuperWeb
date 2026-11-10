@props([
    'name',
    'id' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
])

@php
$inputId = $id ?? 'input-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
$hasError = !empty($error) || $errors->has($name);
$errorMessage = $error ?? $errors->first($name);
$errorId = "{$inputId}-error";
$hintId = "{$inputId}-hint";

$inputClasses = 'block w-full rounded-lg border px-3.5 py-2 text-sm transition-colors focus:outline-none focus-visible:ring-2 disabled:bg-slate-100 dark:disabled:bg-slate-800 disabled:cursor-not-allowed';
if ($hasError) {
    $inputClasses .= ' border-red-600 text-red-900 dark:text-red-300 focus-visible:ring-red-500 focus-visible:border-red-600 bg-red-50 dark:bg-red-950/20';
} else {
    $inputClasses .= ' border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus-visible:ring-blue-600 focus-visible:border-blue-600';
}
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full space-y-1.5']) }}>
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $inputId }}"
            value="{{ old($name, $value) }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required aria-required="true" @endif
            @if($disabled) disabled @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @elseif($hint) aria-describedby="{{ $hintId }}" @endif
            {{ $attributes->except('class') }}
            class="{{ $inputClasses }}"
        />
    </div>

    @if($hasError)
        <p id="{{ $errorId }}" class="text-xs font-medium text-red-600 dark:text-red-400" role="alert">
            {{ $errorMessage }}
        </p>
    @elseif($hint)
        <p id="{{ $hintId }}" class="text-xs text-slate-500 dark:text-slate-400">
            {{ $hint }}
        </p>
    @endif
</div>
