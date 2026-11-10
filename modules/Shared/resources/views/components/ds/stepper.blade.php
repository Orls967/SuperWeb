@props([
    'steps' => [], // list of ['title' => ..., 'status' => 'completed'|'current'|'upcoming']
])

<nav aria-label="Progress">
    <ol role="list" class="space-y-4 md:flex md:space-x-8 md:space-y-0">
        @foreach($steps as $index => $step)
            @php
                $stepNum = $index + 1;
                $status = $step['status'] ?? 'upcoming';
            @endphp
            <li class="md:flex-1">
                @if($status === 'completed')
                    <div class="group flex flex-col border-l-4 border-blue-600 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4">
                        <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">Langkah {{ $stepNum }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $step['title'] }}</span>
                    </div>
                @elseif($status === 'current')
                    <div class="flex flex-col border-l-4 border-blue-600 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" aria-current="step">
                        <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">Langkah {{ $stepNum }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100 font-bold">{{ $step['title'] }}</span>
                    </div>
                @else
                    <div class="group flex flex-col border-l-4 border-slate-200 dark:border-slate-700 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Langkah {{ $stepNum }}</span>
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $step['title'] }}</span>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
