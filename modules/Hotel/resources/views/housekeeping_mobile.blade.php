@extends('layouts.app')

@section('content')
<!-- Tampilan Mobile-First 375px untuk Housekeeping Lapangan -->
<div class="py-4 max-w-md mx-auto px-4 space-y-4">
    <div class="border-b border-slate-200 pb-3">
        <h1 class="text-xl font-bold tracking-tight text-slate-900">Papan Tugas Housekeeping</h1>
        <p class="text-xs text-slate-500">Rute kerja terurut berdasarkan prioritas VIP dan kredit H3.</p>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-emerald-50 p-3 border border-emerald-200 text-xs text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="space-y-3">
        @forelse($tasks as $t)
            <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-mono font-bold text-slate-400">Urutan #{{ $t->route_sequence }}</span>
                        <h2 class="text-2xl font-extrabold text-slate-900">Kamar {{ $t->room->room_number ?? $t->room_id }}</h2>
                    </div>
                    <div class="text-right">
                        @if($t->is_vip)
                            <span class="inline-flex rounded bg-purple-100 text-purple-800 px-2 py-0.5 text-xs font-bold">VIP</span>
                        @elseif($t->is_priority)
                            <span class="inline-flex rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-bold">PRIO</span>
                        @endif
                        <div class="text-xs text-slate-500 mt-1">{{ $t->credit_points }} Kredit</div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="inline-flex items-center rounded px-2 py-1 text-xs font-medium bg-slate-100 text-slate-700 uppercase">
                        {{ $t->status }}
                    </span>

                    <form method="POST" action="{{ route('hotel.housekeeping.tasks.status', $t->id) }}" class="flex items-center gap-1">
                        @csrf
                        @if($t->status === 'assigned')
                            <button type="submit" name="status" value="in_progress" class="rounded bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white">Mulai Bersihkan</button>
                        @elseif($t->status === 'in_progress')
                            <button type="submit" name="status" value="done" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Selesai</button>
                        @elseif($t->status === 'done')
                            <button type="submit" name="status" value="inspected" class="rounded bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white">Inspeksi Lulus</button>
                        @endif
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-sm text-slate-500">
                Semua tugas kebersihan kamar telah selesai dikerjakan!
            </div>
        @endforelse
    </div>
</div>
@endsection
