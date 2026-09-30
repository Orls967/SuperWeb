<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                    Daftarkan Tenant Mitra Baru
                </h2>
                <p class="text-sm text-slate-500 mt-1">Registrasi entitas usaha tenant komersial Duta Mall</p>
            </div>
            <div>
                <a href="{{ route('mall.tenants.index') }}" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50">
                    &larr; Kembali
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
                <form method="POST" action="{{ route('mall.tenants.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Brand / Toko *</label>
                            <input type="text" name="brand_name" value="{{ old('brand_name') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="contoh: Starbucks, RM Sari Ranah">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama PT / CV / Perusahaan *</label>
                            <input type="text" name="company_name" value="{{ old('company_name') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="contoh: PT Sari Boga Nusantara">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Bisnis *</label>
                            <select name="category" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">NPWP Badan Usaha (Opsional)</label>
                            <input type="text" name="npwp" value="{{ old('npwp') }}" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500" placeholder="00.000.000.0-000.000">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">Kontak Penanggung Jawab (PIC)</h4>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Nama PIC *</label>
                                <input type="text" name="pic_name" value="{{ old('pic_name') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">No. WhatsApp / HP *</label>
                                <input type="text" name="pic_phone" value="{{ old('pic_phone') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Email Resmi *</label>
                                <input type="email" name="pic_email" value="{{ old('pic_email') }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Akun User Portal Tenant (Opsional)</label>
                        <select name="user_id" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Hubungkan dengan akun pengguna (Opsional) --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <a href="{{ route('mall.tenants.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                            Batal
                        </a>
                        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all">
                            Simpan Tenant Baru
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
