@props([
    'items' => [], // list of ['label' => ..., 'url' => ...]
])

<nav class="flex" aria-label="Breadcrumb">
    <ol role="list" class="flex items-center space-x-2 text-xs text-slate-500 dark:text-slate-400">
        <li>
            <div>
                <a href="/dashboard" class="hover:text-slate-700 dark:hover:text-slate-200">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="sr-only">Beranda</span>
                </a>
            </div>
        </li>

        @foreach($items as $item)
            @php
                $isLast = $loop->last || empty($item['url']);
            @endphp
            <li class="flex items-center space-x-2">
                <span class="text-slate-300 dark:text-slate-600">/</span>
                @if($isLast)
                    <span class="font-semibold text-slate-800 dark:text-slate-100" aria-current="page">
                        {{ $item['label'] }}
                    </span>
                @else
                    <a href="{{ $item['url'] }}" class="hover:text-slate-700 dark:hover:text-slate-200">
                        {{ $item['label'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

