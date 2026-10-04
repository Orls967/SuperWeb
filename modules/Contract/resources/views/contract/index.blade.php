@extends('layouts.app')

@section('title', 'Daftar Kontrak')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">📋 Manajemen Kontrak</h1>
            <p class="text-slate-400 text-sm mt-1">Kelola seluruh kontrak bisnis dengan hash-chain audit trail</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('contract.clauses.index') }}"
               class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">
                📚 Library Klausul
            </a>
            <a href="{{ route('contract.templates.index') }}"
               class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">
                🗂 Template
            </a>
            <a href="{{ route('contract.create') }}"
               id="btn-create-contract"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
                + Buat Kontrak
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-lg text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex gap-3 mb-6 flex-wrap">
        <input name="q" value="{{ request('q') }}"
               placeholder="Nomor / judul kontrak..."
               class="flex-1 min-w-48 px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
        <select name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
            <option value="">Semua Status</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="type" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
            <option value="">Semua Jenis</option>
            @foreach($types as $t)
                <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ ucfirst($t->value) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Filter</button>
        @if(request()->hasAny(['q','status','type']))
            <a href="{{ route('contract.index') }}" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Reset</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">No. Kontrak</th>
                    <th class="px-4 py-3 text-left">Judul</th>
                    <th class="px-4 py-3 text-left">Jenis</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Nilai (IDR)</th>
                    <th class="px-4 py-3 text-left">Berakhir</th>
                    <th class="px-4 py-3 text-left">Pihak</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($contracts as $c)
                    @php
                        $statusColor = match($c->status->value) {
                            'draft'       => 'text-slate-400 bg-slate-700/50',
                            'review'      => 'text-yellow-400 bg-yellow-400/10',
                            'negotiation' => 'text-orange-400 bg-orange-400/10',
                            'approved'    => 'text-blue-400 bg-blue-400/10',
                            'signed'      => 'text-indigo-400 bg-indigo-400/10',
                            'active'      => 'text-emerald-400 bg-emerald-400/10',
                            'suspended'   => 'text-amber-400 bg-amber-400/10',
                            'expired'     => 'text-slate-500 bg-slate-700/30',
                            'terminated'  => 'text-red-400 bg-red-400/10',
                            'renewed'     => 'text-teal-400 bg-teal-400/10',
                            default       => 'text-slate-400 bg-slate-700/50',
                        };
                    @endphp
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-4 py-3 font-mono text-indigo-300 text-xs">{{ $c->contract_number }}</td>
                        <td class="px-4 py-3 text-white font-medium max-w-xs truncate">{{ $c->title }}</td>
                        <td class="px-4 py-3 text-slate-300 capitalize">{{ $c->contract_type->value }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ $c->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            @if($c->total_value_idr > 0)
                                Rp {{ number_format($c->total_value_idr) }}
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">
                            {{ $c->end_date?->format('d M Y') ?? '—' }}
                            @if($c->end_date && $c->end_date->isPast() && !in_array($c->status->value, ['expired','terminated','renewed']))
                                <span class="text-red-400 ml-1">⚠</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $c->parties->count() }} pihak</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('contract.show', $c) }}"
                               class="px-3 py-1 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded text-xs transition">
                                Detail →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                            Belum ada kontrak. <a href="{{ route('contract.create') }}" class="text-indigo-400 hover:underline">Buat kontrak pertama</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($contracts->hasPages())
        <div class="mt-6">{{ $contracts->links() }}</div>
    @endif
</div>
@endsection
