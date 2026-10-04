@extends('layouts.app')

@section('title', 'Pohon Entitas Badan Hukum (Legal Entities)')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('party.index') }}" class="text-slate-400 hover:text-slate-200 text-sm transition">← Party Directory</a>
            </div>
            <h1 class="text-2xl font-bold text-white">Legal Entities & Badan Hukum</h1>
            <p class="text-slate-400 text-sm mt-1">Hierarki korporasi, entitas induk, anak perusahaan, dan cabang</p>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-xl text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-rose-500/20 border border-rose-500/40 rounded-xl text-rose-300 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Tree View of Legal Entities --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <span>🏢</span>
                    Struktur Korporasi AutoServe Group
                </h2>

                <div class="space-y-4">
                    @forelse($entities as $entity)
                        <div class="p-4 bg-slate-900/60 border border-slate-700/50 rounded-xl space-y-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-base font-bold text-white">{{ $entity->name }}</span>
                                        @if($entity->short_name)
                                            <span class="text-xs text-slate-400">({{ $entity->short_name }})</span>
                                        @endif
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase">
                                            {{ $entity->entity_type }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">
                                        NPWP: <span class="font-mono text-slate-300">{{ $entity->npwp ?? '—' }}</span> |
                                        Mata Uang: <span class="text-slate-300">{{ $entity->functional_currency }}</span> |
                                        Tahun Fiskal Mulai: <span class="text-slate-300">{{ $entity->fiscal_year_start }}</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Anak Perusahaan / Cabang (Children) --}}
                            @if($entity->children->isNotEmpty())
                                <div class="pl-4 ml-2 border-l-2 border-slate-700 space-y-2 mt-3">
                                    <span class="text-[11px] font-medium uppercase tracking-wider text-slate-500 block">Unit Terafiliasi / Anak Perusahaan:</span>
                                    @foreach($entity->children as $child)
                                        <div class="p-3 bg-slate-800/80 rounded-lg border border-slate-700/40 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-semibold text-white">{{ $child->name }}</span>
                                                <span class="text-[10px] text-slate-400 ml-2">({{ strtoupper($child->entity_type) }})</span>
                                                <div class="text-[11px] text-slate-400">NPWP: {{ $child->npwp ?? '—' }}</div>
                                            </div>
                                            <span class="px-2 py-0.5 bg-slate-700 text-slate-300 rounded text-[10px] uppercase">
                                                {{ $child->functional_currency }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-500">
                            Belum ada entitas badan hukum terdaftar. Buat badan hukum pertama di formulir sebelah kanan.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: Create Form --}}
        <div>
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                    <span>+</span>
                    Tambah Badan Hukum
                </h2>

                <form action="{{ route('party.legal-entities.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Nama Legal Lengkap *</label>
                        <input type="text" name="name" required placeholder="PT AutoServe Ekosistem Digital" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Nama Pendek / Alias</label>
                        <input type="text" name="short_name" placeholder="AED Group" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Tipe Entitas *</label>
                        <select name="entity_type" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                            <option value="company">Holding / Company Induk</option>
                            <option value="subsidiary">Anak Perusahaan (Subsidiary)</option>
                            <option value="branch">Cabang Operasional (Branch)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Induk Entitas (Parent)</label>
                        <select name="parent_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                            <option value="">-- Tidak Ada (Holding Utama) --</option>
                            @foreach($entities as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">NPWP Badan</label>
                        <input type="text" name="npwp" placeholder="01.234.567.8-000.000" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">NIB</label>
                        <input type="text" name="nib" placeholder="9120001234567" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Mata Uang *</label>
                            <input type="text" name="functional_currency" value="IDR" required maxlength="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs uppercase">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Tahun Buku *</label>
                            <input type="text" name="fiscal_year_start" value="01-01" required placeholder="01-01" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500 text-xs">
                        </div>
                    </div>

                    <div class="pt-3">
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-medium transition text-xs shadow-md">
                            Simpan Entitas Hukum
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
