<x-app-layout>
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('resto.outlets.index') }}" class="text-sm text-slate-400 hover:text-white flex items-center gap-1 mb-2">
                    ← Kembali ke Daftar Cabang
                </a>
                <h1 class="text-2xl font-bold text-white tracking-tight">Edit Cabang: {{ $outlet->name }}</h1>
            </div>
        </div>

        <form action="{{ route('resto.outlets.update', $outlet) }}" method="POST" class="bg-slate-900/60 backdrop-blur-md rounded-2xl border border-slate-800 p-6 space-y-6 shadow-xl">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kode Outlet *</label>
                    <input type="text" name="code" value="{{ old('code', $outlet->code) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                    @error('code') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nama Outlet / Fasilitas *</label>
                    <input type="text" name="name" value="{{ old('name', $outlet->name) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                    @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Tipe Fasilitas *</label>
                    <select name="type" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" {{ old('type', $outlet->type->value) === $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Referensi Unit Mall (Opsional)</label>
                    <input type="text" name="mall_unit_ref" value="{{ old('mall_unit_ref', $outlet->mall_unit_ref) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Alamat Lengkap *</label>
                    <textarea name="address" rows="2" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>{{ old('address', $outlet->address) }}</textarea>
                    @error('address') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kota *</label>
                    <input type="text" name="city" value="{{ old('city', $outlet->city) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nomor Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $outlet->phone) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kapasitas Tempat Duduk (Seats)</label>
                    <input type="number" name="seats" value="{{ old('seats', $outlet->seats) }}" min="0" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Jam Buka *</label>
                        <input type="time" name="opens_at" value="{{ old('opens_at', substr($outlet->opens_at, 0, 5)) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Jam Tutup *</label>
                        <input type="time" name="closes_at" value="{{ old('closes_at', substr($outlet->closes_at, 0, 5)) }}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-amber-500 transition" required>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-800">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $outlet->is_active) ? 'checked' : '' }} class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-amber-500 focus:ring-amber-500 focus:ring-offset-slate-900">
                <label for="is_active" class="text-sm font-medium text-slate-300">Status Aktif Beroperasi</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                <a href="{{ route('resto.outlets.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-sm font-medium shadow-lg shadow-amber-500/25 transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
