@extends('layouts.app')

@section('title', 'Diff Versi – ' . $contract->contract_number)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <a href="{{ route('contract.versions', $contract) }}" class="text-slate-400 hover:text-white text-sm transition">← Kembali ke Versi</a>
    <h1 class="text-xl font-bold text-white mt-2">🔍 Diff: v{{ $diff['v1'] }} → v{{ $diff['v2'] }}</h1>
    <p class="text-slate-400 text-sm mt-1">{{ $contract->contract_number }} — {{ $contract->title }}</p>

    <div class="mt-6 bg-slate-900 border border-slate-700 rounded-xl p-5 overflow-x-auto">
        <pre class="text-xs font-mono leading-relaxed">@foreach(explode("\n", $diff['diff']) as $line)@php
    $color = str_starts_with($line, '+') ? 'text-emerald-400 bg-emerald-400/5' :
             (str_starts_with($line, '-') ? 'text-red-400 bg-red-400/5' : 'text-slate-400');
@endphp<span class="{{ $color }} block">{{ $line }}</span>
@endforeach</pre>
    </div>
</div>
@endsection
