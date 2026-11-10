@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'hint' => null,
])

@php
$selectId = $id ?? 'select-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
$hasError = !empty($error) || (isset($errors) && $errors->has($name));
$errorMessage = $error ?? (isset($errors) ? $errors->first($name) : null);
$errorId = "{$selectId}-error";
$hintId = "{$selectId}-hint";
$currentVal = old($name, $selected);

$selectClasses = 'block w-full rounded-lg border px-3.5 py-2 text-sm transition-colors focus:outline-none focus-visible:ring-2 disabled:bg-slate-100 dark:disabled:bg-slate-800 disabled:cursor-not-allowed';
if ($hasError) {
    $selectClasses .= ' border-red-600 text-red-900 dark:text-red-300 focus-visible:ring-red-500 focus-visible:border-red-600 bg-red-50 dark:bg-red-950/20';
} else {
    $selectClasses .= ' border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus-visible:ring-blue-600 focus-visible:border-blue-600';
}
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full space-y-1.5']) }}>
    @if($label)
        <label for="{{ $selectId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $selectId }}"
        @if($required) required aria-required="true" @endif
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @elseif($hint) aria-describedby="{{ $hintId }}" @endif
        {{ $attributes->except('class') }}
        class="{{ $selectClasses }}"
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $val => $text)
            @php
                $optVal = is_int($val) && array_is_list($options) ? $text : $val;
                $optText = $text;
            @endphp
            <option value="{{ $optVal }}" @selected((string)$currentVal === (string)$optVal)>
                {{ $optText }}
            </option>
        @endforeach
    </select>

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

