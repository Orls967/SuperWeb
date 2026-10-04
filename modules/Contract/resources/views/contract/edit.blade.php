@extends('layouts.app')

@section('title', 'Edit Kontrak – ' . $contract->contract_number)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('contract.show', $contract) }}" class="text-slate-400 hover:text-white text-sm transition">← Kembali ke Detail</a>
    <h1 class="text-xl font-bold text-white mt-2">✏️ Edit Kontrak</h1>
    <p class="text-slate-400 text-sm mt-1">{{ $contract->contract_number }}</p>

    @if($errors->any())
        <div class="mt-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contract.update', $contract) }}" class="mt-6 space-y-5">
        @csrf @method('PUT')

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Judul *</label>
                <input name="title" value="{{ old('title', $contract->title) }}" required
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nilai (IDR)</label>
                    <input name="total_value_idr" type="number" min="0"
                           value="{{ old('total_value_idr', $contract->total_value_idr) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Notice Period (hari)</label>
                    <input name="notice_period_days" type="number" min="1"
                           value="{{ old('notice_period_days', $contract->notice_period_days) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tanggal Mulai</label>
                    <input name="start_date" type="date" value="{{ old('start_date', $contract->start_date?->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tanggal Berakhir</label>
                    <input name="end_date" type="date" value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Governing Law</label>
                    <input name="governing_law" value="{{ old('governing_law', $contract->governing_law) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Forum Sengketa</label>
                    <input name="dispute_forum" value="{{ old('dispute_forum', $contract->dispute_forum) }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Isi Kontrak (membuat versi baru jika diubah)</label>
                <textarea name="body" rows="10"
                          class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono focus:ring-1 focus:ring-indigo-500 outline-none">{{ old('body', $contract->current_body) }}</textarea>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('contract.show', $contract) }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Batal</a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
