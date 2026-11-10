@extends('layouts.app')

@section('title', 'Daftar Kontrak')

@section('content')
    <x-enterprise.module-dashboard module="Contract" />

<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">📋 Manajemen Kontrak</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Kelola seluruh kontrak bisnis dengan hash-chain audit trail</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('contract.clauses.index') }}">
                <x-ds.button variant="secondary" size="sm">📚 Library Klausul</x-ds.button>
            </a>
            <a href="{{ route('contract.templates.index') }}">
                <x-ds.button variant="secondary" size="sm">🗂 Template</x-ds.button>
            </a>
            <a href="{{ route('contract.create') }}" id="btn-create-contract">
                <x-ds.button variant="primary" size="sm">+ Buat Kontrak</x-ds.button>
            </a>
        </div>
    </div>

    @if(session('success'))
        <x-ds.toast type="success" :message="session('success')" />
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex gap-3 flex-wrap p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
        <div class="flex-1 min-w-48">
            <x-ds.input
                name="q"
                :value="request('q')"
                placeholder="Nomor / judul kontrak..."
                aria-label="Cari nomor atau judul kontrak"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                name="status"
                :value="request('status')"
                :options="array_merge(['' => 'Semua Status'], collect($statuses)->mapWithKeys(fn($s) => [$s->value => $s->label()])->toArray())"
                aria-label="Filter status kontrak"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                name="type"
                :value="request('type')"
                :options="array_merge(['' => 'Semua Jenis'], collect($types)->mapWithKeys(fn($t) => [$t->value => ucfirst($t->value)])->toArray())"
                aria-label="Filter jenis kontrak"
            />
        </div>
        <div class="flex items-center gap-2">
            <x-ds.button type="submit" variant="primary" size="md">Filter</x-ds.button>
            @if(request()->hasAny(['q','status','type']))
                <a href="{{ route('contract.index') }}" class="px-3 py-2 text-xs font-medium text-slate-500 hover:text-slate-900 dark:hover:text-white transition">Reset</a>
            @endif
        </div>
    </form>

    {{-- Table Container --}}
    <x-ds.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 uppercase text-xs border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3">No. Kontrak</th>
                        <th class="px-4 py-3">Judul</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Nilai (IDR)</th>
                        <th class="px-4 py-3">Berakhir</th>
                        <th class="px-4 py-3">Pihak</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($contracts as $c)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-mono text-blue-600 dark:text-blue-400 text-xs font-semibold">{{ $c->contract_number }}</td>
                            <td class="px-4 py-3 text-slate-900 dark:text-white font-medium max-w-xs truncate">{{ $c->title }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300 capitalize">{{ $c->contract_type->value }}</td>
                            <td class="px-4 py-3">
                                <x-ds.badge-status :status="$c->status->value" />
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                @if($c->total_value_idr > 0)
                                    Rp {{ number_format($c->total_value_idr) }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">
                                {{ $c->end_date?->format('d M Y') ?? '—' }}
                                @if($c->end_date && $c->end_date->isPast() && !in_array($c->status->value, ['expired','terminated','renewed']))
                                    <span class="text-red-500 ml-1">⚠</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $c->parties->count() }} pihak</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('contract.show', $c) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                    Detail &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8">
                                <x-ds.empty
                                    title="Belum Ada Kontrak"
                                    description="Belum ada rekaman kontrak bisnis yang terdaftar dalam sistem."
                                    action-label="Buat Kontrak Pertama"
                                    :action-url="route('contract.create')"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contracts->hasPages())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">{{ $contracts->links() }}</div>
        @endif
    </x-ds.card>
</div>
@endsection
