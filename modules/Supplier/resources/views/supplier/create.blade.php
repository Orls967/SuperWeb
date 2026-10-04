@extends('layouts.app')
@section('title', 'Pemasok Baru')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-white">🚚 Registrasi Pemasok</h1>
        <a href="{{ route('supplier.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Kembali</a>
    </div>

    <form method="POST" action="{{ route('supplier.store') }}" class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Nama Pemasok / Produsen *</label>
                <input name="name" required value="{{ old('name') }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                @error('name') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Jenis *</label>
                <select name="kind" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach(['producer' => 'Produsen', 'supplier' => 'Pemasok', 'distributor' => 'Distributor', 'agent' => 'Agen'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('kind', 'supplier') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Tautan Party (Fase 27)</label>
                <select name="party_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">— tanpa tautan —</option>
                    @foreach(\Modules\Party\Domain\Models\Party::where('is_active', true)->orderBy('name')->get() as $party)
                        <option value="{{ $party->id }}" @selected(old('party_id') === $party->id)>{{ $party->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Akun Portal (pemilik data)</label>
                <select name="owner_user_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">— belum ditentukan —</option>
                    @foreach(\App\Models\User::orderBy('name')->get() as $user)
                        <option value="{{ $user->id }}" @selected(old('owner_user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Lead Time (hari)</label>
                <input name="lead_time_days" type="number" min="1" value="{{ old('lead_time_days', 7) }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Termin Bayar (hari)</label>
                <input name="payment_terms_days" type="number" min="0" value="{{ old('payment_terms_days', 30) }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Kemampuan (pisahkan dengan koma)</label>
                <input name="capabilities[]" placeholder="Pangan beku, Minuman kemasan, Bumbu dapur" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                <input name="capabilities[]" placeholder="Kategori kedua (opsional)" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm mt-2">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Catatan</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="flex gap-3">
            <button class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium">Daftarkan</button>
            <a href="{{ route('supplier.index') }}" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Batal</a>
        </div>
    </form>
</div>
@endsection
