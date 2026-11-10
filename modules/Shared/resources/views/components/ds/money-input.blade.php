@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'currency' => 'IDR',
    'required' => false,
    'placeholder' => '0',
    'hint' => null,
])

@php
$moneyId = $id ?? 'money-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
$rawVal = old($name, $value ?? 0);
$initialAmount = is_numeric($rawVal) ? (int)$rawVal : 0;
@endphp

<div x-data="{
    raw: {{ $initialAmount }},
    formatted: '',
    init() {
        this.format();
    },
    format() {
        let num = parseInt(this.raw, 10);
        if (isNaN(num)) num = 0;
        this.raw = num;
        this.formatted = new Intl.NumberFormat('id-ID').format(num);
    },
    update(e) {
        let clean = e.target.value.replace(/[^0-9]/g, '');
        let num = parseInt(clean, 10);
        this.raw = isNaN(num) ? 0 : num;
        this.format();
    }
}" class="w-full space-y-1.5">
    @if($label)
        <label for="{{ $moneyId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative rounded-lg shadow-sm">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $currency }}</span>
        </div>

        <input type="hidden" name="{{ $name }}" :value="raw" />

        <input
            type="text"
            id="{{ $moneyId }}"
            :value="formatted"
            @input="update($event)"
            placeholder="{{ $placeholder }}"
            @if($required) required aria-required="true" @endif
            class="block w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 py-2 pl-12 pr-3.5 text-right tabular-nums text-sm font-semibold text-slate-900 dark:text-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
        />
    </div>

    @if($hint)
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>

