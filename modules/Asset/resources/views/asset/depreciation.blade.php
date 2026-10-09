@extends('layouts.app')
@section('title', 'Riwayat Penyusutan')
@section('subtitle', 'Buku komersial dan fiskal (simulasi)')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6"><h1 class="text-2xl font-bold text-white">📉 Riwayat Penyusutan</h1><a href="{{ route('asset.audit') }}" class="text-indigo-300 hover:underline">← Audit Aset</a></div>
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm"><thead class="bg-slate-900/60 text-xs text-slate-400 uppercase"><tr><th class="px-4 py-3 text-left">Aset</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Buku</th><th class="px-4 py-3">Metode</th><th class="px-4 py-3 text-right">Penyusutan</th><th class="px-4 py-3 text-right">Akumulasi</th></tr></thead><tbody class="divide-y divide-slate-700/60">
            @forelse($records as $r)<tr><td class="px-4 py-3 text-indigo-300">{{ $r->asset?->asset_number }} · {{ $r->asset?->name }}</td><td class="px-4 py-3 text-center">{{ $r->period }}</td><td class="px-4 py-3 text-center uppercase">{{ $r->book }}</td><td class="px-4 py-3 text-center">{{ $r->method->label() }}</td><td class="px-4 py-3 text-right">{{ number_format($r->amount_idr) }}</td><td class="px-4 py-3 text-right">{{ number_format($r->accumulated_after_idr) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada penyusutan tercatat.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</div>
@endsection
