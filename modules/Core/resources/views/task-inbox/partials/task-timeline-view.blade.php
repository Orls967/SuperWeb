{{-- modules/Core/resources/views/task-inbox/partials/task-timeline-view.blade.php --}}
@props([
    'tasks' => [],
])

@php
    $tasksCollection = collect($tasks);
    $groupedByUrgency = [
        'overdue' => $tasksCollection->filter(fn($t) => !empty($t->due_date) && \Carbon\Carbon::parse($t->due_date)->isPast()),
        'today' => $tasksCollection->filter(fn($t) => !empty($t->due_date) && \Carbon\Carbon::parse($t->due_date)->isToday()),
        'this_week' => $tasksCollection->filter(fn($t) => !empty($t->due_date) && \Carbon\Carbon::parse($t->due_date)->isCurrentWeek() && !\Carbon\Carbon::parse($t->due_date)->isToday() && !\Carbon\Carbon::parse($t->due_date)->isPast()),
        'later' => $tasksCollection->filter(fn($t) => empty($t->due_date) || \Carbon\Carbon::parse($t->due_date)->isAfter(\Carbon\Carbon::now()->endOfWeek())),
    ];
@endphp

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-8" role="region" aria-label="Tampilan Linimasa Tugas">
    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900 tracking-tight flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Linimasa Tenggat & Beban Kerja Tugas
            </h3>
            <p class="text-xs text-gray-500 mt-1">
                Visualisasi terstruktur jadwal penyelesaian tugas berdasarkan status batas waktu (SLA) dan prioritas sistemik.
            </p>
        </div>

        <div class="flex items-center space-x-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                Total {{ $tasksCollection->count() }} Tugas
            </span>
        </div>
    </div>

    {{-- Overdue Section --}}
    @if($groupedByUrgency['overdue']->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-rose-600 -ml-5"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-rose-700">
                    Terlewat Batas Waktu (Overdue) — {{ $groupedByUrgency['overdue']->count() }}
                </h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($groupedByUrgency['overdue'] as $task)
                    <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/50 hover:bg-rose-50 transition-colors shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-16 h-16 pointer-events-none overflow-hidden">
                            <div class="bg-rose-500 text-white text-[9px] font-bold py-0.5 right-[-35px] top-[15px] w-[120px] text-center transform rotate-45 shadow">
                                KRITIS
                            </div>
                        </div>

                        <div class="flex items-start justify-between">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-200">
                                {{ $task->module_code ?? 'CORE' }}
                            </span>
                            <span class="text-xs font-semibold text-rose-600 mr-8">
                                {{ \Carbon\Carbon::parse($task->due_date)->diffForHumans() }}
                            </span>
                        </div>

                        <h5 class="mt-2 text-sm font-bold text-gray-900 line-clamp-1">
                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="hover:text-rose-600 hover:underline">
                                {{ $task->title }}
                            </a>
                        </h5>

                        <p class="text-xs text-gray-600 mt-1 line-clamp-2">
                            {{ $task->description ?? 'Tidak ada ringkasan detail tugas.' }}
                        </p>

                        <div class="mt-3 pt-3 border-t border-rose-100 flex items-center justify-between text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $task->assignee_user_id ? 'ID: ' . $task->assignee_user_id : 'Belum Ditugaskan' }}
                            </span>

                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="font-semibold text-rose-600 hover:text-rose-800">
                                Selesaikan Segera &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Today Section --}}
    @if($groupedByUrgency['today']->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-800">
                    Jatuh Tempo Hari Ini — {{ $groupedByUrgency['today']->count() }}
                </h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($groupedByUrgency['today'] as $task)
                    <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/40 hover:bg-amber-50 transition-colors shadow-sm">
                        <div class="flex items-start justify-between">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">
                                {{ $task->module_code ?? 'CORE' }}
                            </span>
                            <span class="text-xs font-semibold text-amber-700">
                                {{ \Carbon\Carbon::parse($task->due_date)->format('H:i') }} WIB
                            </span>
                        </div>

                        <h5 class="mt-2 text-sm font-bold text-gray-900 line-clamp-1">
                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="hover:text-amber-600 hover:underline">
                                {{ $task->title }}
                            </a>
                        </h5>

                        <p class="text-xs text-gray-600 mt-1 line-clamp-2">
                            {{ $task->description ?? 'Tidak ada ringkasan detail tugas.' }}
                        </p>

                        <div class="mt-3 pt-3 border-t border-amber-100 flex items-center justify-between text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $task->assignee_user_id ? 'ID: ' . $task->assignee_user_id : 'Belum Ditugaskan' }}
                            </span>

                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="font-semibold text-amber-700 hover:text-amber-900">
                                Buka Detail &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- This Week Section --}}
    @if($groupedByUrgency['this_week']->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-blue-800">
                    Minggu Ini — {{ $groupedByUrgency['this_week']->count() }}
                </h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($groupedByUrgency['this_week'] as $task)
                    <div class="p-4 rounded-xl border border-blue-100 bg-white hover:border-blue-300 hover:shadow-sm transition-all">
                        <div class="flex items-start justify-between">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                                {{ $task->module_code ?? 'CORE' }}
                            </span>
                            <span class="text-xs font-medium text-gray-500">
                                {{ \Carbon\Carbon::parse($task->due_date)->translatedFormat('l, d M') }}
                            </span>
                        </div>

                        <h5 class="mt-2 text-sm font-semibold text-gray-900 line-clamp-1">
                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="hover:text-blue-600 hover:underline">
                                {{ $task->title }}
                            </a>
                        </h5>

                        <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                            {{ $task->description ?? 'Tidak ada ringkasan detail tugas.' }}
                        </p>

                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $task->assignee_user_id ? 'ID: ' . $task->assignee_user_id : 'Belum Ditugaskan' }}
                            </span>

                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="font-medium text-blue-600 hover:text-blue-800">
                                Tinjau &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Later / Unscheduled Section --}}
    @if($groupedByUrgency['later']->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-600">
                    Masa Mendatang / Belum Terjadwal — {{ $groupedByUrgency['later']->count() }}
                </h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($groupedByUrgency['later'] as $task)
                    <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-white hover:border-gray-300 transition-all">
                        <div class="flex items-start justify-between">
                            <span class="text-[10px] font-medium uppercase px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                                {{ $task->module_code ?? 'CORE' }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ !empty($task->due_date) ? \Carbon\Carbon::parse($task->due_date)->format('d/m/Y') : 'Tanpa Tenggat' }}
                            </span>
                        </div>

                        <h5 class="mt-2 text-sm font-medium text-gray-800 line-clamp-1">
                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="hover:text-gray-900 hover:underline">
                                {{ $task->title }}
                            </a>
                        </h5>

                        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                            <span>Prioritas: {{ ucfirst($task->priority ?? 'normal') }}</span>
                            <a href="{{ route('core.task-inbox.show', $task->id) }}" class="text-gray-600 hover:text-gray-900">
                                Detail &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

