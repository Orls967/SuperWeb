@extends('layouts.app')

@section('title', 'Activity Feed')
@section('subtitle', 'Riwayat aktivitas terbaru')

@section('content')
    <x-enterprise.module-dashboard module="Core" />

<div class="space-y-6">
    <x-ds.breadcrumb :items="[
        ['label' => 'Beranda', 'url' => route('dashboard')],
        ['label' => 'Feed Aktivitas', 'url' => '#', 'active' => true],
    ]" />

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Activity Feed</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ auth()->user()->isAdmin() ? 'Semua aktivitas platform real-time' : 'Aktivitas akun Anda' }}</p>
        </div>
        <x-ds.badge-status status="released" />
    </div>

    <x-ds.card title="Log Aktivitas Peristiwa (Event Spine)">
        @if($activities->isEmpty())
            <x-ds.empty
                title="Belum Ada Aktivitas Tercatat"
                description="Peristiwa transaksi dan tindakan pengguna akan muncul di sini secara kronologis."
            />
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($activities as $activity)
                    <div class="flex items-start gap-4 py-4 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        {{-- Module Badge --}}
                        <div class="shrink-0 mt-0.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                {{ $activity->module }}
                            </span>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $activity->description }}</p>
                            <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                @if($activity->user)
                                    <span>{{ $activity->user->name }}</span>
                                    <span>&bull;</span>
                                @endif
                                <span>{{ $activity->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        {{-- Event Type --}}
                        <div class="shrink-0">
                            <span class="text-[10px] font-mono text-slate-400 uppercase bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">
                                {{ str_replace('_', ' ', $activity->event) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ds.card>
</div>
@endsection
