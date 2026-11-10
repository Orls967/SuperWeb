@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => 'Pilih atau cari...',
    'required' => false,
])

@php
$comboboxId = $id ?? 'combo-'.str_replace(['[', ']', '_'], '-', $name).'-'.uniqid();
$initialVal = old($name, $value);
@endphp

<div x-data="{
    open: false,
    search: '',
    selectedVal: '{{ $initialVal }}',
    selectedLabel: '',
    options: {{ json_encode($options) }},
    init() {
        if (this.selectedVal && this.options[this.selectedVal]) {
            this.selectedLabel = this.options[this.selectedVal];
        } else {
            this.selectedLabel = this.selectedVal ? this.selectedVal : '';
        }
    },
    select(k, v) {
        this.selectedVal = k;
        this.selectedLabel = v;
        this.open = false;
    }
}" class="relative w-full space-y-1.5" @keydown.escape.window="open = false">
    @if($label)
        <label for="{{ $comboboxId }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="selectedVal" />

    <div class="relative">
        <button
            type="button"
            id="{{ $comboboxId }}"
            @click="open = !open"
            aria-haspopup="listbox"
            :aria-expanded="open"
            class="flex w-full items-center justify-between rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3.5 py-2 text-left text-sm text-slate-900 dark:text-slate-100 shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
        >
            <span x-text="selectedLabel || '{{ $placeholder }}'" :class="{'text-slate-400 dark:text-slate-500': !selectedLabel}"></span>
            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-1 shadow-lg focus:outline-none text-sm"
            role="listbox"
        >
            <div class="p-1.5 border-b border-slate-100 dark:border-slate-700">
                <input
                    type="text"
                    x-model="search"
                    placeholder="Saring pilihan..."
                    aria-label="Saring pilihan"
                    class="w-full rounded border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            <template x-for="(label, key) in options" :key="key">
                <div
                    x-show="!search || label.toLowerCase().includes(search.toLowerCase())"
                    @click="select(key, label)"
                    role="option"
                    class="cursor-pointer px-3.5 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 select-none text-slate-800 dark:text-slate-200"
                    :class="{'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium': selectedVal === key}"
                >
                    <span x-text="label"></span>
                </div>
            </template>
        </div>
    </div>
</div>
