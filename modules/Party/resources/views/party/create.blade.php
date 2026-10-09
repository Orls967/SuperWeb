@extends('layouts.app')

@section('title', 'Tambah Party Baru')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('party.index') }}" class="text-slate-400 hover:text-slate-200 text-sm transition">← Party Directory</a>
            </div>
            <h1 class="text-2xl font-bold text-white">Tambah Party Baru</h1>
            <p class="text-slate-400 text-sm mt-1">Registrasi identitas entitas bisnis atau perorangan</p>
        </div>
    </div>

    {{-- Error Alert --}}
    @if($errors->any())
        <div class="mb-6 p-4 bg-rose-500/20 border border-rose-500/40 rounded-xl text-rose-300 text-sm">
            <p class="font-semibold mb-1">Periksa kembali isian formulir:</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('party.store') }}" method="POST" class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-6 space-y-6">
        @csrf

        {{-- Section 1: Profil Utama --}}
        <div>
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                Identitas Utama
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="type" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Tipe Party *</label>
                    <select id="type" name="type" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                        <option value="company" @selected(old('type') === 'company')>Perusahaan (Company)</option>
                        <option value="person" @selected(old('type') === 'person')>Perorangan (Person)</option>
                    </select>
                </div>

                <div>
                    <label for="name" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Nama Lengkap / Badan Usaha *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="PT Sumber Berkah Sejahtera" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label for="short_name" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Nama Singkat / Alias</label>
                    <input type="text" id="short_name" name="short_name" value="{{ old('short_name') }}" placeholder="SBS Logistics" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label for="legal_entity_id" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Legal Entity Terafiliasi</label>
                    <select id="legal_entity_id" name="legal_entity_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                        <option value="">-- Tanpa Legal Entity Terikat --</option>
                        @foreach($legalEntities as $le)
                            <option value="{{ $le->id }}" @selected(old('legal_entity_id') === $le->id)>{{ $le->name }} ({{ strtoupper($le->entity_type) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <hr class="border-slate-700/50">

        {{-- Section 2: Identifikasi Pajak & Legalitas --}}
        <div>
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Nomor Identitas & Pajak
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="npwp" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">NPWP (15/16 digit)</label>
                    <input type="text" id="npwp" name="npwp" value="{{ old('npwp') }}" placeholder="01.234.567.8-901.000" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Digunakan untuk dedup check otomatis.</p>
                </div>

                <div>
                    <label for="nib" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">NIB (Nomor Induk Berusaha)</label>
                    <input type="text" id="nib" name="nib" value="{{ old('nib') }}" placeholder="9120001234567" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label for="nik" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">NIK (KTP Perorangan)</label>
                    <input type="text" id="nik" name="nik" value="{{ old('nik') }}" placeholder="3271012345670001" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
            </div>
        </div>

        <hr class="border-slate-700/50">

        {{-- Section 3: Peran & Kredit Awal --}}
        <div>
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                Peran Bisnis & Limit Kredit
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Peran Awal</label>
                    <select id="role" name="role" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                        <option value="">-- Pilih Peran Utama --</option>
                        @foreach(['supplier','carrier','tenant','customer','distributor','agent','partner','franchisee'] as $r)
                            <option value="{{ $r }}" @selected(old('role') === $r)>{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="credit_limit_idr" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-2">Limit Kredit Awal (IDR)</label>
                    <input type="number" id="credit_limit_idr" name="credit_limit_idr" value="{{ old('credit_limit_idr', 0) }}" min="0" step="100000" placeholder="0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Dikelola dalam integer Rupiah murni.</p>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-700/50">
            <a href="{{ route('party.index') }}" class="px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg text-sm font-medium transition">
                Batal
            </a>
            <button type="submit" id="btn-submit-party" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition shadow-lg shadow-indigo-600/30">
                Simpan & Screening Party
            </button>
        </div>
    </form>
</div>
@endsection
