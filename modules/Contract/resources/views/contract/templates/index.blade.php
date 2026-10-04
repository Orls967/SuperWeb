@extends('layouts.app')

@section('title', 'Template Kontrak')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">🗂 Template Kontrak</h1>
            <p class="text-slate-400 text-sm mt-1">Template menetapkan susunan klausul standar dan variabel yang dibutuhkan</p>
        </div>
        <a href="{{ route('contract.templates.create') }}"
           id="btn-create-template"
           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
            + Template Baru
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-lg text-emerald-300 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-4">
        @forelse($templates as $tpl)
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 hover:border-indigo-500/50 transition">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-mono text-indigo-300 text-xs">{{ $tpl->code }}</span>
                    <span class="text-xs text-slate-400 capitalize">{{ str_replace('_', ' ', is_string($tpl->contract_type) ? $tpl->contract_type : $tpl->contract_type->value) }}</span>
                </div>
                <h3 class="text-white font-medium mb-2">{{ $tpl->name }}</h3>
                @if($tpl->description)
                    <p class="text-slate-400 text-xs mb-3">{{ Str::limit($tpl->description, 80) }}</p>
                @endif
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span>{{ count($tpl->default_clause_ids ?? []) }} klausul</span>
                    @if(!empty($tpl->required_variables))
                        <span>{{ count($tpl->required_variables) }} variabel</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-12 text-slate-500 text-sm">
                Belum ada template. <a href="{{ route('contract.templates.create') }}" class="text-indigo-400 hover:underline">Buat template pertama</a>.
            </div>
        @endforelse
    </div>
</div>
@endsection
