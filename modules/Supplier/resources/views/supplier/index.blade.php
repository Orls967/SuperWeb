@extends('layouts.app')

@section('title', 'Pemasok')
@section('subtitle', 'Direktori pemasok & produsen (kualifikasi, harga, skor, risiko)')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">🚚 Pemasok & Produsen</h1>
            <p class="text-sm text-slate-400 mt-1">Onboarding, sertifikasi, harga bertingkat, skor periodik, dan manajemen risiko.</p>
        </div>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('supplier.index') }}" class="flex gap-2">
                <input name="q" value="{{ request('q') }}" placeholder="Cari kode/nama..." class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm w-48">
                <select name="status" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">Semua status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Filter</button>
            </form>
            <form method="POST" action="{{ route('supplier.scan-risks') }}">
                @csrf
                <button class="px-4 py-2 bg-red-600/80 hover:bg-red-500 text-white rounded-lg text-sm">🔍 Pindai Risiko</button>
            </form>
            <a href="{{ route('supplier.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium">+ Pemasok Baru</a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Kode</th>
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Jenis</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Lead Time</th>
                    <th class="px-4 py-3 text-right">Termin</th>
                    <th class="px-4 py-3 text-right">Rating</th>
                    <th class="px-4 py-3 text-right">Risiko</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/60">
                @forelse($suppliers as $supplier)
                    <tr class="hover:bg-slate-700/30">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-300"><a href="{{ route('supplier.show', $supplier) }}">{{ $supplier->code }}</a></td>
                        <td class="px-4 py-3 text-slate-200">{{ $supplier->name }}</td>
                        <td class="px-4 py-3 text-slate-400 capitalize">{{ $supplier->kind }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded {{ $supplier->status->value === 'preferred' ? 'bg-emerald-500/15 text-emerald-300' : ($supplier->status->value === 'disqualified' ? 'bg-red-500/15 text-red-300' : ($supplier->status->value === 'approved' ? 'bg-indigo-500/15 text-indigo-300' : 'bg-amber-500/15 text-amber-300')) }}">{{ $supplier->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ $supplier->lead_time_days }} h</td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ $supplier->payment_terms_days }} h</td>
                        <td class="px-4 py-3 text-right text-amber-300">{{ $supplier->rating }}★</td>
                        <td class="px-4 py-3 text-right">{{ $supplier->riskFlags()->count() ? '<span class="text-red-400 font-semibold">'.$supplier->riskFlags()->count().'</span>' : '0' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500 text-sm">Belum ada pemasok terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $suppliers->links() }}</div>
</div>
@endsection
