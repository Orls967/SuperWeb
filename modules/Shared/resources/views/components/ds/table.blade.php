@props([
    'headers' => [], // list of ['key' => ..., 'label' => ..., 'sortable' => bool, 'align' => 'left'|'right', 'tabular' => bool]
    'rows' => [],
    'paginator' => null,
    'exportable' => false,
    'exportUrl' => null,
    'queryState' => null,
    'emptyMessage' => 'Tidak ada data ditemukan.',
])

@php
$state = $queryState;
@endphp

<div class="w-full space-y-4">
    @if($exportable && $exportUrl)
        <div class="flex items-center justify-end">
            <x-ds.button variant="secondary" size="sm" :href="$exportUrl">
                <svg class="h-4 w-4 mr-1.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Ekspor CSV
            </x-ds.button>
        </div>
    @endif

    {{-- Tampilan Desktop (Tabel Standar) --}}
    <div class="hidden md:block overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm" role="table">
            <thead class="bg-slate-50 dark:bg-slate-900 sticky top-0 z-10">
                <tr>
                    @foreach($headers as $h)
                        @php
                            $isSortable = (bool)($h['sortable'] ?? false);
                            $key = $h['key'] ?? '';
                            $align = $h['align'] ?? 'left';
                            $isSorted = $state && $state->isSortedBy($key);
                            $direction = $state ? $state->getSortDirection() : 'asc';
                            $oppositeDir = $state ? $state->getOppositeDirection($key) : 'asc';
                        @endphp
                        <th
                            scope="col"
                            class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-300 {{ $align === 'right' ? 'text-right' : 'text-left' }}"
                            @if($isSortable) aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif
                        >
                            @if($isSortable && $state)
                                <a
                                    href="?{{ $state->buildUrlParams(['sort' => $key, 'direction' => $oppositeDir, 'page' => 1]) }}"
                                    class="group inline-flex items-center gap-1.5 hover:text-blue-600 dark:hover:text-blue-400 focus:outline-none"
                                >
                                    <span>{{ $h['label'] }}</span>
                                    <span class="text-slate-400 group-hover:text-blue-600">
                                        @if($isSorted)
                                            {{ $direction === 'asc' ? '▲' : '▼' }}
                                        @else
                                            <span class="opacity-30 group-hover:opacity-100">↕</span>
                                        @endif
                                    </span>
                                </a>
                            @else
                                <span>{{ $h['label'] }}</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                @forelse($rows as $row)
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/50 transition-colors">
                        @if(isset($slot) && !empty(trim((string)$slot)))
                            {{ $slot($row) }}
                        @else
                            @foreach($headers as $h)
                                @php
                                    $key = $h['key'];
                                    $val = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                                    $align = $h['align'] ?? 'left';
                                    $isTabular = (bool)($h['tabular'] ?? false);
                                @endphp
                                <td class="px-4 py-3 {{ $align === 'right' ? 'text-right' : 'text-left' }} {{ $isTabular ? 'tabular-nums font-mono' : '' }}">
                                    {{ $val }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Tampilan Mobile 375 px (Transformasi ke Kartu Vertikal Tanpa Horizontal Scroll) --}}
    <div class="block md:hidden space-y-3">
        @forelse($rows as $row)
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 shadow-sm space-y-2">
                @foreach($headers as $h)
                    @php
                        $key = $h['key'];
                        $val = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                        $isTabular = (bool)($h['tabular'] ?? false);
                    @endphp
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-100 dark:border-slate-700/50 last:border-none">
                        <span class="font-medium text-slate-500 dark:text-slate-400">{{ $h['label'] }}</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 {{ $isTabular ? 'tabular-nums font-mono' : '' }}">
                            {{ $val }}
                        </span>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ $emptyMessage }}
            </div>
        @endforelse
    </div>

    {{-- Pagination Bar --}}
    @if($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
        <div class="pt-2">
            {{ $paginator->links() }}
        </div>
    @endif
</div>

