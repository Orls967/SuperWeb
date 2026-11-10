@props([
    'filters' => [], // list of ['name' => ..., 'label' => ..., 'type' => 'select'|'text', 'options' => [], 'value' => ...]
    'action' => '',
    'resetUrl' => '',
    'searchPlaceholder' => 'Cari data...',
])

<form method="GET" action="{{ $action }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 items-end">
        {{-- Input Pencarian Utama --}}
        <div>
            <x-ds.input
                name="q"
                label="Pencarian"
                placeholder="{{ $searchPlaceholder }}"
                value="{{ request('q') }}"
            />
        </div>

        {{-- Filter Dinamis --}}
        @foreach($filters as $filter)
            @php
                $fName = $filter['name'];
                $fLabel = $filter['label'];
                $fType = $filter['type'] ?? 'text';
            @endphp
            <div>
                @if($fType === 'select')
                    <x-ds.select
                        :name="$fName"
                        :label="$fLabel"
                        :options="$filter['options'] ?? []"
                        :selected="request($fName, $filter['value'] ?? '')"
                        placeholder="Semua {{ $fLabel }}"
                    />
                @else
                    <x-ds.input
                        :name="$fName"
                        :label="$fLabel"
                        value="{{ request($fName, $filter['value'] ?? '') }}"
                    />
                @endif
            </div>
        @endforeach

        {{-- Tombol Aksi Filter --}}
        <div class="flex items-center gap-2">
            <x-ds.button variant="primary" type="submit" class="w-full sm:w-auto">
                Terapkan
            </x-ds.button>
            @if($resetUrl)
                <x-ds.button variant="ghost" :href="$resetUrl" class="w-full sm:w-auto">
                    Reset
                </x-ds.button>
            @endif
        </div>
    </div>
</form>

