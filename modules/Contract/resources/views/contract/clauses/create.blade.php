@extends('layouts.app')

@section('title', 'Tambah Klausul Baru')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('contract.clauses.index') }}" class="text-slate-400 hover:text-white text-sm transition">← Library Klausul</a>
    <h1 class="text-xl font-bold text-white mt-2">📝 Tambah Klausul Baru</h1>
    <p class="text-slate-400 text-sm mt-1">Klausul mendukung variabel placeholder <code class="text-indigo-300">{{"{{"}}nama_variabel{{"}}"}}</code></p>

    @if($errors->any())
        <div class="mt-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contract.clauses.store') }}" class="mt-6 space-y-5">
        @csrf
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kode Unik * (mis. CL-FORCE-MAJEURE)</label>
                    <input name="code" value="{{ old('code') }}" required
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono uppercase focus:ring-1 focus:ring-indigo-500 outline-none"
                           placeholder="CL-CONFIDENTIALITY">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kategori *</label>
                    <select name="category" required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(old('category') == $cat)>{{ ucfirst(str_replace('_',' ',$cat)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Judul Klausul *</label>
                    <input name="title" value="{{ old('title') }}" required
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none"
                           placeholder="Pasal: Kerahasiaan Informasi">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Isi Template *</label>
                    <textarea name="body_template" rows="10" required
                              class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono focus:ring-1 focus:ring-indigo-500 outline-none"
                              placeholder="Para pihak sepakat bahwa {{"{{"}}party.name{{"}}"}} akan menjaga kerahasiaan...">{{ old('body_template') }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Gunakan <code class="text-indigo-300">{{"{{"}}nama{{"}}"}} </code> sebagai placeholder variabel.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_standard" id="is_standard" value="1" @checked(old('is_standard', true))
                           class="w-4 h-4 text-indigo-600 bg-slate-900 border-slate-600 rounded">
                    <label for="is_standard" class="text-sm text-white cursor-pointer">Klausul Standar</label>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('contract.clauses.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Batal</a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Tambah ke Library</button>
        </div>
    </form>
</div>
@endsection
