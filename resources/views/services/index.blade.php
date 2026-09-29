@extends('layouts.app')

@section('title', 'Master Data - Jenis Servis')
@section('subtitle', 'Kelola jenis servis bengkel')

@section('content')
<div class="space-y-6">

    {{-- Form Tambah --}}
    <div class="glass-card rounded-2xl p-6" x-data="{ showForm: false }">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-white">Jenis Servis</h2>
            <button @click="showForm = !showForm" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-blue-500 to-violet-600 text-white text-sm font-medium hover:from-blue-600 hover:to-violet-700 transition-all shadow-lg shadow-blue-500/25">
                <svg class="w-4 h-4 transition-transform" :class="showForm ? 'rotate-45' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span x-text="showForm ? 'Tutup' : 'Tambah Servis'"></span>
            </button>
        </div>

        <div x-show="showForm" x-cloak x-transition class="mb-6 p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
            <form action="{{ route('services.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Nama Servis</label>
                        <input type="text" name="name" required class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all" placeholder="Tune Up Mesin">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Biaya Jasa (Rp)</label>
                        <input type="number" name="price" required min="0" class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all" placeholder="350000">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5">Deskripsi</label>
                        <input type="text" name="description" class="w-full px-4 py-3 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all" placeholder="Keterangan servis">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-medium bg-emerald-500 hover:bg-emerald-600 text-white transition-all">Simpan</button>
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider border-b border-slate-700/50">
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Nama Servis</th>
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3">Biaya Jasa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @forelse($services as $i => $service)
                    <tr class="hover:bg-slate-800/50 transition-colors" x-data="{ editing: false }">
                        <td class="px-4 py-3 text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-3">
                            <span x-show="!editing" class="text-white font-medium">{{ $service->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $service->description ?? '-' }}</td>
                        <td class="px-4 py-3 text-emerald-400 font-medium">Rp {{ number_format($service->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $service->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                {{ $service->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <form action="{{ route('services.destroy', $service) }}" method="POST" class="inline" onsubmit="return confirm('Hapus servis ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg text-red-400/60 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada data servis</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
