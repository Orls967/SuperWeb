@extends('layouts.app')

@section('title', 'Activity Feed')
@section('subtitle', 'Riwayat aktivitas terbaru')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Activity Feed</h2>
        <p class="text-sm text-slate-400">{{ auth()->user()->isAdmin() ? 'Semua aktivitas platform' : 'Aktivitas Anda' }}</p>
    </div>

    <div class="glass-card rounded-2xl overflow-hidden">
        @forelse($activities as $activity)
        <div class="flex items-start gap-4 px-6 py-4 border-b border-slate-700/30 last:border-b-0 hover:bg-slate-800/30 transition-colors">
            {{-- Module Badge --}}
            <div class="flex-shrink-0 mt-0.5">
                @php
                    $moduleColors = [
                        'autoserve' => 'bg-blue-500/10 text-blue-400',
                        'autodex' => 'bg-violet-500/10 text-violet-400',
                        'banking' => 'bg-indigo-500/10 text-indigo-400',
                        'payment' => 'bg-emerald-500/10 text-emerald-400',
                        'store' => 'bg-amber-500/10 text-amber-400',
                        'crypto' => 'bg-orange-500/10 text-orange-400',
                        'finance' => 'bg-pink-500/10 text-pink-400',
                    ];
                    $color = $moduleColors[$activity->module] ?? 'bg-slate-500/10 text-slate-400';
                @endphp
                <span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider {{ $color }}">
                    {{ $activity->module }}
                </span>
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <p class="text-sm text-white">{{ $activity->description }}</p>
                <div class="flex items-center gap-2 mt-1">
                    @if($activity->user)
                    <span class="text-xs text-slate-500">{{ $activity->user->name }}</span>
                    <span class="text-slate-600">·</span>
                    @endif
                    <span class="text-xs text-slate-500">{{ $activity->created_at->diffForHumans() }}</span>
                </div>
            </div>

            {{-- Event Type --}}
            <div class="flex-shrink-0">
                <span class="text-[10px] font-mono text-slate-600 uppercase">{{ str_replace('_', ' ', $activity->event) }}</span>
            </div>
        </div>
        @empty
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-slate-400">Belum ada aktivitas tercatat.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
