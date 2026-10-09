@extends('layouts.app')

@section('title', 'Edit Klausul – ' . $clause->code)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('contract.clauses.index') }}" class="text-slate-400 hover:text-white text-sm transition">← Library Klausul</a>
    <h1 class="text-xl font-bold text-white mt-2">✏️ Edit Klausul — <span class="font-mono text-indigo-300">{{ $clause->code }}</span></h1>
    <p class="text-slate-400 text-sm mt-1">Menyimpan perubahan akan menaikkan nomor versi klausul ini.</p>

    @if($errors->any())
        <div class="mt-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contract.clauses.update', $clause) }}" class="mt-6 space-y-5">
        @csrf @method('PUT')
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Judul Klausul *</label>
                    <input name="title" value="{{ old('title', $clause->title) }}" required
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kategori *</label>
                    <select name="category" required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected(old('category', $clause->category) == $cat)>{{ ucfirst(str_replace('_',' ',$cat)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2 mt-4">
                    <span class="text-xs text-slate-400">Versi saat ini: <strong class="text-white">v{{ $clause->version }}</strong></span>
                    <span class="text-xs text-slate-500">→ akan menjadi v{{ $clause->version + 1 }}</span>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Isi Template *</label>
                    <textarea name="body_template" rows="12" required
                              class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono focus:ring-1 focus:ring-indigo-500 outline-none">{{ old('body_template', $clause->body_template) }}</textarea>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('contract.clauses.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Batal</a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Simpan (Bump v{{ $clause->version + 1 }})</button>
        </div>
    </form>
</div>
@endsection
