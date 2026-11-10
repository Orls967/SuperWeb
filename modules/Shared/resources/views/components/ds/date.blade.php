@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'required' => false,
    'min' => null,
    'max' => null,
])

@php
$dateId = $id ?? 'date-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
$val = old($name, $value);
if ($val instanceof \DateTimeInterface) {
    $val = $val->format('Y-m-d');
}
@endphp

<div class="w-full space-y-1.5">
    @if($label)
        <label for="{{ $dateId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <input
            type="date"
            name="{{ $name }}"
            id="{{ $dateId }}"
            value="{{ $val }}"
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @if($required) required aria-required="true" @endif
            {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3.5 py-2 text-sm text-slate-900 dark:text-slate-100 shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600']) }}
        />
    </div>
</div>

