@extends('layouts.app')

@section('title', 'Library Klausul Kontrak')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">📚 Library Klausul</h1>
            <p class="text-slate-400 text-sm mt-1">Klausul standar ber-versi yang dapat digunakan di template & kontrak</p>
        </div>
        <a href="{{ route('contract.clauses.create') }}"
           id="btn-create-clause"
           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
            + Klausul Baru
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-lg text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="flex gap-3 mb-6">
        <input name="q" value="{{ request('q') }}" placeholder="Cari kode/judul..."
               class="flex-1 min-w-40 px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
        <select name="category" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
            <option value="">Semua Kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected(request('category') == $cat)>{{ ucfirst(str_replace('_',' ',$cat)) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Filter</button>
    </form>

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Kode</th>
                    <th class="px-4 py-3 text-left">Judul</th>
                    <th class="px-4 py-3 text-left">Kategori</th>
                    <th class="px-4 py-3 text-left">Versi</th>
                    <th class="px-4 py-3 text-left">Standard</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($clauses as $clause)
                    <tr class="hover:bg-slate-700/20">
                        <td class="px-4 py-3 font-mono text-indigo-300 text-xs">{{ $clause->code }}</td>
                        <td class="px-4 py-3 text-white">{{ $clause->title }}</td>
                        <td class="px-4 py-3 text-slate-400 text-xs capitalize">{{ str_replace('_',' ',$clause->category) }}</td>
                        <td class="px-4 py-3 text-slate-400 text-xs">v{{ $clause->version }}</td>
                        <td class="px-4 py-3 text-xs">
                            @if($clause->is_standard)
                                <span class="text-emerald-400">✓ Standar</span>
                            @else
                                <span class="text-slate-500">Custom</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('contract.clauses.edit', $clause) }}"
                               class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs transition">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500 text-sm">
                            Belum ada klausul. <a href="{{ route('contract.clauses.create') }}" class="text-indigo-400 hover:underline">Tambah klausul pertama</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($clauses->hasPages())
        <div class="mt-6">{{ $clauses->links() }}</div>
    @endif
</div>
@endsection
