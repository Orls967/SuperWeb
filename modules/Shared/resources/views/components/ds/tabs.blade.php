@props([
    'tabs' => [], // list of ['key' => ..., 'label' => ..., 'count' => int|null, 'active' => bool, 'url' => string|null]
    'activeTab' => null,
])

<div class="border-b border-slate-200 dark:border-slate-700">
    <nav class="-mb-px flex space-x-6" aria-label="Tabs" role="tablist">
        @foreach($tabs as $tab)
            @php
                $key = $tab['key'] ?? '';
                $isActive = $activeTab ? ($activeTab === $key) : ($tab['active'] ?? false);
                $url = $tab['url'] ?? null;
            @endphp

            @if($url)
                <a
                    href="{{ $url }}"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    class="group inline-flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-medium transition-colors select-none {{ $isActive ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-semibold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-700 dark:hover:text-slate-200' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    @if(isset($tab['count']))
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $isActive ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ $tab['count'] }}
                        </span>
                    @endif
                </a>
            @else
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    @click="$dispatch('tab-selected', '{{ $key }}')"
                    class="group inline-flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-medium transition-colors select-none {{ $isActive ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-semibold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-700 dark:hover:text-slate-200' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    @if(isset($tab['count']))
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $isActive ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ $tab['count'] }}
                        </span>
                    @endif
                </button>
            @endif
        @endforeach
    </nav>
</div>
