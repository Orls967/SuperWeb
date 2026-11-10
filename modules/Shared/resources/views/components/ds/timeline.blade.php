@props([
    'events' => [],
])

<div class="flow-root">
    <ul role="list" class="-mb-8">
        @forelse($events as $event)
            @php
                $isLast = $loop->last;
                $dateStr = \Modules\Shared\Application\Ui\IdFormatter::formatDate($event->created_at ?? $event['created_at'] ?? null, 'human');
                $role = $event->actor_role ?? $event['actor_role'] ?? 'system';
                $reason = $event->reason ?? $event['reason'] ?? '';
                $from = $event->from_status ?? $event['from_status'] ?? null;
                $to = $event->to_status ?? $event['to_status'] ?? null;
            @endphp
            <li>
                <div class="relative pb-8">
                    @if(!$isLast)
                        <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
                    @endif
                    <div class="relative flex space-x-3">
                        <div>
                            <span class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 flex items-center justify-center ring-8 ring-white dark:ring-slate-800 text-xs font-bold">
                                {{ strtoupper(substr($role, 0, 2)) }}
                            </span>
                        </div>
                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $reason }}
                                </p>
                                @if($from || $to)
                                    <div class="mt-1 flex items-center gap-2 text-xs">
                                        @if($from)
                                            <x-ds.badge-status :status="$from" size="sm" />
                                            <span class="text-slate-400">→</span>
                                        @endif
                                        @if($to)
                                            <x-ds.badge-status :status="$to" size="sm" />
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="whitespace-nowrap text-right text-xs text-slate-500 dark:text-slate-400">
                                <time>{{ $dateStr }}</time>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        @empty
            <li class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">
                Belum ada catatan riwayat peristiwa.
            </li>
        @endforelse
    </ul>
</div>

