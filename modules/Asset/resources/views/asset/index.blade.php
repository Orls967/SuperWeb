@extends('layouts.app')

@section('title', 'Register Aset')
@section('subtitle', 'Daftar aset grup (PSAK 16 simulasi)')

@section('content')
    <x-enterprise.module-dashboard module="Asset" />

<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">🗄️ Register Aset</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kategori, umur ekonomis & metode penyusutan adalah simulasi PSAK 16.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('asset.audit') }}">
                <x-ds.button variant="secondary" size="sm">📊 Audit & TCO</x-ds.button>
            </a>

            <form method="POST" action="{{ route('asset.verify-chain') }}" class="inline">
                @csrf
                <x-ds.button type="submit" variant="secondary" size="sm">🔗 Verify Chain</x-ds.button>
            </form>

            <a href="{{ route('asset.create') }}">
                <x-ds.button variant="primary" size="sm">+ Aset Baru</x-ds.button>
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('asset.index') }}" class="flex flex-wrap gap-3 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
        <div class="flex-1 min-w-48">
            <x-ds.input
                name="q"
                :value="request('q')"
                placeholder="Cari nomor/tag/nama..."
                aria-label="Cari nomor, tag, atau nama aset"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                name="status"
                :value="request('status')"
                :options="array_merge(['' => 'Semua status'], collect($statuses)->mapWithKeys(fn($st) => [$st->value => $st->label()])->toArray())"
                aria-label="Filter status aset"
            />
        </div>
        <div class="w-48">
            <x-ds.select
                name="category_id"
                :value="request('category_id')"
                :options="array_merge(['' => 'Semua kategori'], collect($categories)->mapWithKeys(fn($cat) => [$cat->id => $cat->name])->toArray())"
                aria-label="Filter kategori aset"
            />
        </div>
        <div class="flex items-center gap-2">
            <x-ds.button type="submit" variant="primary" size="md">Filter</x-ds.button>
            @if(request()->hasAny(['q','status','category_id']))
                <a href="{{ route('asset.index') }}" class="px-3 py-2 text-xs font-medium text-slate-500 hover:text-slate-900 dark:hover:text-white transition">Reset</a>
            @endif
        </div>
    </form>

    @if(session('success'))
        <x-ds.toast type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-ds.toast type="danger" :message="session('error')" />
    @endif

    {{-- Kontainer Tabel Aset --}}
    <x-ds.card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 text-xs uppercase border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Lokasi</th>
                        <th class="px-4 py-3 text-right">Book Value (IDR)</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Kondisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($assets as $asset)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 font-semibold">
                                <a href="{{ route('asset.show', $asset) }}" class="hover:underline">{{ $asset->asset_number }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-900 dark:text-slate-200 font-medium">{{ $asset->name }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $asset->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $asset->location?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-900 dark:text-white font-mono font-medium">{{ number_format($asset->book_value_idr) }}</td>
                            <td class="px-4 py-3">
                                <x-ds.badge-status :status="$asset->status->value" />
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 capitalize">{{ $asset->condition }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8">
                                <x-ds.empty
                                    title="Belum Ada Aset Terdaftar"
                                    description="Belum ada data register aset. Tambahkan aset baru untuk memulai pencatatan."
                                    action-label="+ Tambah Aset"
                                    :action-url="route('asset.create')"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($assets->hasPages())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">{{ $assets->links() }}</div>
        @endif
    </x-ds.card>
</div>
@endsection
