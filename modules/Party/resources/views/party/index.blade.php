@extends('layouts.app')

@section('title', 'Party Directory')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Party Directory</h1>
            <p class="text-slate-400 text-sm mt-1">Satu sumber kebenaran untuk semua pihak bisnis</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('party.legal-entities') }}"
               class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">
                🏢 Legal Entities
            </a>
            <a href="{{ route('party.create') }}"
               id="btn-create-party"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
                + Tambah Party
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-lg text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-6">
        <input id="search-party" name="q" value="{{ request('q') }}"
               placeholder="Cari nama / NIB..."
               class="px-3 py-2 bg-slate-800 border border-slate-700 text-white rounded-lg text-sm w-60 focus:outline-none focus:border-indigo-500">
        <select id="filter-status" name="status" class="px-3 py-2 bg-slate-800 border border-slate-700 text-white rounded-lg text-sm">
            <option value="">Semua Status</option>
            @foreach(['pending','verified','suspended','blacklisted'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select id="filter-role" name="role" class="px-3 py-2 bg-slate-800 border border-slate-700 text-white rounded-lg text-sm">
            <option value="">Semua Role</option>
            @foreach(['supplier','carrier','tenant','customer','distributor','agent','partner','franchisee'] as $r)
                <option value="{{ $r }}" @selected(request('role') === $r)>{{ ucfirst($r) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Filter</button>
        <a href="{{ route('party.index') }}" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-sm transition">Reset</a>
    </form>

    {{-- Table --}}
    <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-slate-400 text-xs uppercase">
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Tipe</th>
                    <th class="px-4 py-3 text-left">Role(s)</th>
                    <th class="px-4 py-3 text-left">Status KYB</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Credit Score</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($parties as $party)
                <tr class="hover:bg-slate-700/30 transition">
                    <td class="px-4 py-3">
                        <div class="font-medium text-white">{{ $party->name }}</div>
                        @if($party->nib)
                            <div class="text-xs text-slate-500">NIB: {{ $party->nib }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-300">{{ ucfirst($party->type->value) }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($party->roles->where('is_active', true)->take(3) as $role)
                                <span class="px-2 py-0.5 bg-indigo-500/20 text-indigo-300 rounded text-xs">
                                    {{ ucfirst($role->role->value) }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $kybColors = ['pending'=>'amber','in_review'=>'blue','verified'=>'emerald','rejected'=>'red'];
                            $kyb = $party->kyb_status->value;
                            $c = $kybColors[$kyb] ?? 'slate';
                        @endphp
                        <span class="px-2 py-0.5 bg-{{ $c }}-500/20 text-{{ $c }}-300 rounded-full text-xs">
                            {{ ucfirst(str_replace('_',' ',$kyb)) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $stColors = ['pending'=>'amber','verified'=>'emerald','suspended'=>'orange','blacklisted'=>'red'];
                            $st = $party->status->value;
                            $sc = $stColors[$st] ?? 'slate';
                        @endphp
                        <span class="px-2 py-0.5 bg-{{ $sc }}-500/20 text-{{ $sc }}-300 rounded-full text-xs font-medium">
                            {{ ucfirst($st) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($party->creditProfile)
                            <span class="font-mono font-bold text-white">{{ $party->creditProfile->internal_score }}</span>
                            <span class="text-slate-500 text-xs">/100</span>
                        @else
                            <span class="text-slate-600">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('party.show', $party) }}"
                           class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded text-xs transition">
                            Detail →
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                        Tidak ada party ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $parties->links() }}
    </div>
</div>
@endsection
