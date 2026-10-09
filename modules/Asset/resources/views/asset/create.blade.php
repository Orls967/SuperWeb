@extends('layouts.app')

@section('title', 'Registrasi Aset Baru')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-white">📦 Registrasi Aset</h1>
        <a href="{{ route('asset.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">← Kembali</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-300 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('asset.store') }}" class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Nama Aset *</label>
                <input name="name" required value="{{ old('name') }}" placeholder="Mis. Gudang Distribusi Utara"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                @error('name') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Kategori * (umur ekonomis & metode default otomatis)</label>
                <select name="category_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">— pilih —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>
                            {{ $cat->name }} ({{ $cat->useful_life_years }} th, {{ $cat->depreciation_method }})
                        </option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Lokasi</label>
                <select name="location_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">— tanpa lokasi —</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }} ({{ $loc->level }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Kondisi</label>
                <select name="condition" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    @foreach(['good' => 'Baik', 'fair' => 'Cukup', 'poor' => 'Rusak Ringan', 'broken' => 'Rusak Berat'] as $k => $v)
                        <option value="{{ $k }}" @selected(old('condition', 'good') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Penanggung Jawab (user)</label>
                <select name="responsible_user_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="">— pilih —</option>
                    @foreach(\App\Models\User::orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}" @selected(old('responsible_user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Merek</label>
                <input name="brand" value="{{ old('brand') }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Nomor Seri</label>
                <input name="serial_number" value="{{ old('serial_number') }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Biaya Perolehan (IDR) *</label>
                <input name="acquisition_cost_idr" type="number" min="0" required value="{{ old('acquisition_cost_idr', 0) }}"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                @error('acquisition_cost_idr') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Landed Cost / Biaya Lain (IDR)</label>
                <input name="landed_cost_idr" type="number" min="0" value="{{ old('landed_cost_idr', 0) }}"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Sumber</label>
                <select name="source_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    <option value="direct">Pembelian langsung</option>
                    <option value="purchase">Dari PO/GRN (Fase 34)</option>
                    <option value="construction">Konstruksi / CIP</option>
                </select>
            </div>

            <div>
                <label class="text-xs text-slate-400 block mb-1">Tanggal Perolehan</label>
                <input name="acquired_at" type="date" value="{{ old('acquired_at', now()->toDateString()) }}"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Deskripsi</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">{{ old('description') }}</textarea>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium">Daftarkan Aset</button>
            <a href="{{ route('asset.index') }}" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm">Batal</a>
        </div>
    </form>
</div>
@endsection
