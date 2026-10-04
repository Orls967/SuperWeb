@extends('layouts.app')

@section('title', 'Hash-Chain Versi – ' . $contract->contract_number)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <a href="{{ route('contract.show', $contract) }}" class="text-slate-400 hover:text-white text-sm transition">← Kembali ke Detail</a>
    <div class="flex items-center justify-between mt-2 mb-6">
        <h1 class="text-xl font-bold text-white">🔗 Hash-Chain Explorer — {{ $contract->contract_number }}</h1>
        @if($chainValid)
            <span class="px-3 py-1 bg-emerald-400/10 text-emerald-300 border border-emerald-500/30 rounded-full text-sm">✅ Chain Valid</span>
        @else
            <span class="px-3 py-1 bg-red-400/10 text-red-300 border border-red-500/30 rounded-full text-sm">⚠️ Chain Corrupted</span>
        @endif
    </div>

    @if(!$chainValid && $chainError)
        <div class="mb-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">{{ $chainError }}</div>
    @endif

    {{-- Diff form --}}
    <form method="GET" action="{{ route('contract.version-diff', $contract) }}" class="flex gap-3 mb-6 items-center">
        <label class="text-slate-400 text-sm">Diff antara v</label>
        <input name="v1" type="number" min="1" value="{{ request('v1',1) }}"
               class="w-16 px-2 py-1 bg-slate-800 border border-slate-600 rounded text-white text-sm text-center">
        <label class="text-slate-400 text-sm">dan v</label>
        <input name="v2" type="number" min="2" value="{{ request('v2',2) }}"
               class="w-16 px-2 py-1 bg-slate-800 border border-slate-600 rounded text-white text-sm text-center">
        <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">
            Tampilkan Diff
        </button>
    </form>

    {{-- Version List --}}
    <div class="space-y-3">
        @forelse($versions as $v)
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-indigo-300 font-mono font-bold">v{{ $v->sequence }}</span>
                        <span class="text-xs uppercase bg-slate-700 text-slate-300 px-2 py-0.5 rounded">{{ $v->change_type }}</span>
                        @if($v->created_by_name)
                            <span class="text-xs text-slate-500">oleh {{ $v->created_by_name }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500">{{ $v->created_at?->format('d M Y H:i:s') }}</span>
                </div>
                <div class="space-y-1">
                    <p class="text-xs font-mono text-slate-500">prev: <span class="text-slate-400">{{ substr($v->prev_hash, 0, 24) }}...</span></p>
                    <p class="text-xs font-mono text-slate-500">hash: <span class="text-indigo-300">{{ $v->hash }}</span></p>
                </div>
                @if(!empty($v->metadata))
                    <p class="text-xs text-slate-500 mt-2">Meta: {{ json_encode($v->metadata) }}</p>
                @endif
            </div>
        @empty
            <div class="text-center text-slate-500 py-8 text-sm">Belum ada versi.</div>
        @endforelse
    </div>

    @if($versions->hasPages())
        <div class="mt-6">{{ $versions->links() }}</div>
    @endif
</div>
@endsection
