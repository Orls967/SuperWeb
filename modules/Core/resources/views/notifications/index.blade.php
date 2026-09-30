@extends('layouts.app')

@section('title', 'Notifikasi')
@section('subtitle', 'Semua pemberitahuan Anda')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Notifikasi</h2>
            <p class="text-sm text-slate-400">{{ $unreadCount }} belum dibaca</p>
        </div>
        @if($unreadCount > 0)
        <form action="{{ route('notifications.markAllRead') }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 border border-slate-700 text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Tandai Semua Dibaca
            </button>
        </form>
        @endif
    </div>

    {{-- Notification List --}}
    <div class="space-y-2">
        @forelse($notifications as $notif)
        <div class="glass-card rounded-2xl p-4 hover:border-slate-600/50 transition-all {{ $notif->isRead() ? 'opacity-60' : '' }}">
            <div class="flex items-start gap-4">
                {{-- Icon --}}
                <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center
                    @switch($notif->icon)
                        @case('success') bg-emerald-500/10 @break
                        @case('warning') bg-amber-500/10 @break
                        @case('danger') bg-red-500/10 @break
                        @default bg-blue-500/10
                    @endswitch
                ">
                    @switch($notif->icon)
                        @case('success')
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @case('warning')
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            @break
                        @case('danger')
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @default
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endswitch
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $notif->title }}</p>
                            <p class="text-sm text-slate-400 mt-0.5">{{ $notif->body }}</p>
                        </div>
                        @if(!$notif->isRead())
                            <span class="flex-shrink-0 w-2 h-2 rounded-full bg-blue-400 mt-2"></span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 mt-2">
                        <span class="text-xs text-slate-500">{{ $notif->created_at->diffForHumans() }}</span>
                        @if($notif->action_url)
                        <form action="{{ route('notifications.read', $notif) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                                {{ $notif->action_label ?? 'Lihat Detail' }} →
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="glass-card rounded-2xl p-12 text-center">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <p class="text-slate-400">Belum ada notifikasi.</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($notifications->hasPages())
    <div class="flex justify-center">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
@endsection
