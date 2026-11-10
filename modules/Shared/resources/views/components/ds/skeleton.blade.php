@props([
    'type' => 'card', // card, table, text, avatar
    'rows' => 3,
    'columns' => 4,
])

@if($type === 'table')
    <div class="w-full space-y-3 animate-pulse">
        <div class="h-10 bg-slate-200 dark:bg-slate-700 rounded-lg w-full"></div>
        @for($i = 0; $i < $rows; $i++)
            <div class="flex gap-4">
                @for($j = 0; $j < $columns; $j++)
                    <div class="h-8 bg-slate-100 dark:bg-slate-800 rounded flex-1"></div>
                @endfor
            </div>
        @endfor
    </div>
@elseif($type === 'avatar')
    <div class="flex items-center space-x-4 animate-pulse">
        <div class="rounded-full bg-slate-200 dark:bg-slate-700 h-10 w-10"></div>
        <div class="flex-1 space-y-2 py-1">
            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
            <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-1/2"></div>
        </div>
    </div>
@elseif($type === 'text')
    <div class="space-y-2.5 animate-pulse">
        @for($i = 0; $i < $rows; $i++)
            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded {{ $i === $rows - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
@else
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 animate-pulse space-y-4">
        <div class="h-5 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
        <div class="h-8 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
        <div class="h-4 bg-slate-100 dark:bg-slate-800 rounded w-full"></div>
    </div>
@endif
