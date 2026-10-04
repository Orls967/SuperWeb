@extends('layouts.app')

@section('title', 'Buat Kontrak Baru')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('contract.index') }}" class="text-slate-400 hover:text-white text-sm transition">← Kembali ke Daftar</a>
        <h1 class="text-2xl font-bold text-white mt-2">📋 Buat Kontrak Baru</h1>
        <p class="text-slate-400 text-sm mt-1">Nomor kontrak akan dibuat otomatis (gapless) via Core DocumentNumbering</p>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contract.store') }}" x-data="contractForm()" class="space-y-6">
        @csrf

        {{-- Entitas Hukum & Jenis --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-2">📌 Identitas Kontrak</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Judul Kontrak *</label>
                    <input name="title" value="{{ old('title') }}" required
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none"
                           placeholder="Perjanjian Distribusi Eksklusif Wilayah Kalimantan...">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Entitas Hukum *</label>
                    <select name="legal_entity_id" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        <option value="">Pilih entitas...</option>
                        @foreach($legalEntities as $le)
                            <option value="{{ $le->id }}" @selected(old('legal_entity_id') == $le->id)>
                                {{ $le->name }} ({{ $le->short_name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Jenis Kontrak *</label>
                    <select name="contract_type" required
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        <option value="">Pilih jenis...</option>
                        @foreach($types as $t)
                            <option value="{{ $t->value }}" @selected(old('contract_type') == $t->value)>{{ ucfirst(str_replace('_', ' ', $t->value)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Template --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-2">🗂 Template (Opsional)</h2>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Gunakan Template Kontrak</label>
                <select name="template_id"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                    <option value="">— Tanpa template (klausul kosong) —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}" @selected(old('template_id') == $tpl->id)>
                            {{ $tpl->name }} ({{ $tpl->contract_type }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Template akan menyertakan klausul-klausul standar secara otomatis.</p>
            </div>
        </div>

        {{-- Nilai & Mata Uang --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-2">💰 Nilai Kontrak</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-slate-400 mb-1">Nilai Kontrak (IDR Integer)</label>
                    <input name="total_value_idr" type="number" value="{{ old('total_value_idr', 0) }}" min="0"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none"
                           placeholder="0">
                    <p class="text-xs text-slate-500 mt-1">Nilai ≥ Rp 100.000.000 memerlukan persetujuan Admin.</p>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Mata Uang</label>
                    <select name="currency"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        <option value="IDR" @selected(old('currency','IDR')=='IDR')>IDR</option>
                        <option value="USD" @selected(old('currency')=='USD')>USD</option>
                        <option value="EUR" @selected(old('currency')=='EUR')>EUR</option>
                        <option value="SGD" @selected(old('currency')=='SGD')>SGD</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Periode --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-2">📅 Periode & Perpanjangan</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tanggal Mulai</label>
                    <input name="start_date" type="date" value="{{ old('start_date') }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Tanggal Berakhir</label>
                    <input name="end_date" type="date" value="{{ old('end_date') }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Notice Period (hari)</label>
                    <input name="notice_period_days" type="number" value="{{ old('notice_period_days', 30) }}" min="1"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Perpanjangan Otomatis</label>
                    <div class="flex items-center gap-3 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew'))
                                   x-model="autoRenew"
                                   class="w-4 h-4 text-indigo-600 bg-slate-900 border-slate-600 rounded">
                            <span class="text-white text-sm">Perpanjang otomatis</span>
                        </label>
                    </div>
                </div>
                <div x-show="autoRenew" x-transition>
                    <label class="block text-xs text-slate-400 mb-1">Periode Perpanjangan (bulan)</label>
                    <input name="renewal_period_months" type="number" value="{{ old('renewal_period_months', 12) }}" min="1"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
            </div>
        </div>

        {{-- Hukum --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4">
            <h2 class="text-base font-semibold text-white border-b border-slate-700 pb-2">⚖️ Hukum & Yurisdiksi</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Governing Law</label>
                    <input name="governing_law" value="{{ old('governing_law', 'Indonesia') }}"
                           class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Forum Penyelesaian Sengketa</label>
                    <select name="dispute_forum"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm focus:ring-1 focus:ring-indigo-500 outline-none">
                        <option value="BANI Jakarta" @selected(old('dispute_forum','BANI Jakarta')=='BANI Jakarta')>BANI Jakarta</option>
                        <option value="BANI Surabaya" @selected(old('dispute_forum')=='BANI Surabaya')>BANI Surabaya</option>
                        <option value="ICC" @selected(old('dispute_forum')=='ICC')>ICC</option>
                        <option value="SIAC" @selected(old('dispute_forum')=='SIAC')>SIAC</option>
                        <option value="Pengadilan Negeri" @selected(old('dispute_forum')=='Pengadilan Negeri')>Pengadilan Negeri</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Pihak --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-4" x-data="partyList()">
            <div class="flex items-center justify-between border-b border-slate-700 pb-2">
                <h2 class="text-base font-semibold text-white">👥 Pihak-Pihak Kontrak</h2>
                <button type="button" @click="addParty()"
                        class="px-3 py-1 bg-indigo-600/20 hover:bg-indigo-600/40 text-indigo-300 rounded text-sm transition">
                    + Tambah Pihak
                </button>
            </div>
            <p class="text-xs text-slate-500">Minimal 2 pihak diperlukan sebelum kontrak dapat diajukan peninjauan.</p>
            <div class="space-y-3">
                <template x-for="(party, i) in parties" :key="i">
                    <div class="flex gap-3 items-start bg-slate-900/50 rounded-lg p-3">
                        <div class="flex-1">
                            <label class="block text-xs text-slate-500 mb-1">Pihak</label>
                            <select :name="'parties['+i+'][party_id]'"
                                    class="w-full px-2 py-1.5 bg-slate-800 border border-slate-600 rounded text-white text-sm">
                                @foreach($parties as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Peran</label>
                            <select :name="'parties['+i+'][role]'"
                                    class="px-2 py-1.5 bg-slate-800 border border-slate-600 rounded text-white text-sm">
                                @foreach($roles as $r)
                                    <option value="{{ $r->value }}">{{ ucfirst(str_replace('_',' ',$r->value)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-20">
                            <label class="block text-xs text-slate-500 mb-1">Urutan Tanda Tangan</label>
                            <input type="number" :name="'parties['+i+'][signing_order]'" :value="i+1" min="1"
                                   class="w-full px-2 py-1.5 bg-slate-800 border border-slate-600 rounded text-white text-sm">
                        </div>
                        <button type="button" @click="removeParty(i)"
                                class="mt-5 text-red-400 hover:text-red-300 text-sm">✕</button>
                    </div>
                </template>
                <div x-show="parties.length === 0" class="text-slate-500 text-sm text-center py-4">
                    Klik "+ Tambah Pihak" untuk menambahkan pihak kontrak.
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('contract.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">Batal</a>
            <button type="submit" id="btn-submit-contract"
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition">
                Buat Kontrak →
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function contractForm() {
        return { autoRenew: {{ old('auto_renew') ? 'true' : 'false' }} };
    }
    function partyList() {
        return {
            parties: [],
            addParty() { this.parties.push({ party_id: '', role: 'second_party' }); },
            removeParty(i) { this.parties.splice(i, 1); }
        };
    }
</script>
@endpush
@endsection
