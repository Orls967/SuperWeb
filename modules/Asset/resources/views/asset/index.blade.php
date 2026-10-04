@extends('layouts.app')

@section('title', 'Register Aset')
@section('subtitle', 'Daftar aset grup (PSAK 16 simulasi)')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">🗄️ Register Aset</h1>
            <p class="text-sm text-slate-400 mt-1">Kategori, umur ekonomis & metode penyusutan adalah simulasi PSAK 16.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <form method="GET" action="{{ route('asset.index') }}" class="flex gap-2 items-center">
                <input name="q" value="{{ request('q') }}" placeholder="Cari nomor/tag/nama..."
                       class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm w-52">
                <select name="status" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">Semua status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">Semua kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int) request('category_id') === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Filter</button>
            </form>

            <form method="POST" action="{{ route('asset.verify-chain') }}">
                @csrf
                <button class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">🔗 Verify Chain</button>
            </form>

            <a href="{{ route('asset.create') }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium">+ Aset Baru</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-300 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-300 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Nomor</th>
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Kategori</th>
                    <th class="px-4 py-3 text-left">Lokasi</th>
                    <th class="px-4 py-3 text-right">Book Value (IDR)</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Kondisi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
                @forelse($assets as $asset)
                    <tr class="hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-300">
                            <a href="{{ route('asset.show', $asset) }}">{{ $asset->asset_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-200">{{ $asset->name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $asset->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $asset->location?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-white">{{ number_format($asset->book_value_idr) }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded {{ $asset->status->value === 'disposed' ? 'bg-red-500/15 text-red-300' : ($asset->status->value === 'in_use' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300') }}">{{ $asset->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 capitalize">{{ $asset->condition }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500 text-sm">Belum ada aset. Jalankan <code class="text-indigo-300">php artisan ast:backfill-links</code> atau tambah aset baru.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assets->links() }}</div>
</div>
@endsection
