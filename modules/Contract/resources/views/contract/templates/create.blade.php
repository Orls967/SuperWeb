@extends('layouts.app')

@section('title', 'Buat Template Kontrak')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('contract.templates.index') }}" class="text-slate-400 hover:text-white text-sm transition">← Template Library</a>
    <h1 class="text-xl font-bold text-white mt-2">🗂 Buat Template Kontrak</h1>

    @if($errors->any())
        <div class="mt-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contract.templates.store') }}" class="mt-6 space-y-5">
        @csrf
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kode Unik *</label>
                    <input name="code" value="{{ old('code') }}" required placeholder="TPL-PURCHASE-STD"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono uppercase focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nama Template *</label>
                    <input name="name" value="{{ old('name') }}" required placeholder="Kontrak Pembelian Standar"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Jenis Kontrak *</label>
                    <select name="contract_type" required class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        @foreach($types as $t)
                            <option value="{{ $t->value }}" @selected(old('contract_type') == $t->value)>{{ ucfirst(str_replace('_',' ',$t->value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Variabel Wajib (pisah koma)</label>
                    <input name="required_variables" value="{{ old('required_variables') }}"
                           placeholder="party.name, effective_date, amount"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Deskripsi</label>
                    <textarea name="description" rows="2"
                              class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none"
                              placeholder="Deskripsi singkat template...">{{ old('description') }}</textarea>
                </div>
            </div>

            {{-- Clause Selection --}}
            <div>
                <label class="block text-xs text-slate-400 mb-2">Klausul (urut tampil)</label>
                <div class="space-y-2 max-h-64 overflow-y-auto bg-slate-900/50 rounded-lg p-3">
                    @foreach($clauses->groupBy('category') as $cat => $group)
                        <p class="text-xs text-slate-500 uppercase mt-2 mb-1">{{ str_replace('_',' ',$cat) }}</p>
                        @foreach($group as $clause)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-800/50 p-1.5 rounded">
                                <input type="checkbox" name="default_clause_ids[]" value="{{ $clause->id }}"
                                       @checked(in_array($clause->id, old('default_clause_ids', [])))
                                       class="w-4 h-4 text-indigo-600 bg-slate-900 border-slate-600 rounded">
                                <span class="font-mono text-indigo-300 text-xs">{{ $clause->code }}</span>
                                <span class="text-white text-sm">{{ $clause->title }}</span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('contract.templates.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Batal</a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Buat Template</button>
        </div>
    </form>
</div>
@endsection
