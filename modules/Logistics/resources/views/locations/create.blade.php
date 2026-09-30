<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-100 leading-tight">
                {{ __('Tambah Titik Lokasi Baru') }}
            </h2>
            <a href="{{ route('logistics.locations.index') }}" class="text-sm text-slate-400 hover:text-slate-200">
                ← Kembali ke Daftar
            </a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl backdrop-blur-xl shadow-xl p-8">
            <form method="POST" action="{{ route('logistics.locations.store') }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kode Lokasi (Unik)</label>
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="contoh: HUB-BDJ, PORT-IDBDJ" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('code') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nama Lokasi</label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="contoh: Hub Logistik Banjarmasin Basirih" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Tipe Fasilitas</label>
                        <select name="type" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none">
                            @foreach($types as $type)
                                <option value="{{ $type->value }}" {{ old('type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('type') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Zona Waktu</label>
                        <select name="timezone" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none">
                            <option value="Asia/Makassar" {{ old('timezone', 'Asia/Makassar') === 'Asia/Makassar' ? 'selected' : '' }}>WITA (Asia/Makassar)</option>
                            <option value="Asia/Jakarta" {{ old('timezone') === 'Asia/Jakarta' ? 'selected' : '' }}>WIB (Asia/Jakarta)</option>
                            <option value="Asia/Jayapura" {{ old('timezone') === 'Asia/Jayapura' ? 'selected' : '' }}>WIT (Asia/Jayapura)</option>
                            <option value="Asia/Singapore" {{ old('timezone') === 'Asia/Singapore' ? 'selected' : '' }}>Singapore (Asia/Singapore)</option>
                            <option value="Asia/Shanghai" {{ old('timezone') === 'Asia/Shanghai' ? 'selected' : '' }}>China (Asia/Shanghai)</option>
                        </select>
                        @error('timezone') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">UN/LOCODE (Opsional, 5 Huruf)</label>
                        <input type="text" name="unlocode" value="{{ old('unlocode') }}" maxlength="5" placeholder="contoh: IDBDJ, IDTPP" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('unlocode') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">IATA Code (Opsional, 3 Huruf)</label>
                        <input type="text" name="iata" value="{{ old('iata') }}" maxlength="3" placeholder="contoh: BDJ, CGK" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('iata') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kota</label>
                        <input type="text" name="city" value="{{ old('city') }}" required placeholder="contoh: Banjarmasin" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('city') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Provinsi</label>
                        <input type="text" name="province" value="{{ old('province') }}" required placeholder="contoh: Kalimantan Selatan" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('province') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Negara (ISO 2-Huruf)</label>
                        <input type="text" name="country" value="{{ old('country', 'ID') }}" maxlength="2" required placeholder="ID" class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('country') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Waktu Koneksi Minimum (Menit)</label>
                        <input type="number" name="min_connection_minutes" value="{{ old('min_connection_minutes', 60) }}" min="0" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('min_connection_minutes') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Latitude (Desimal)</label>
                        <input type="number" step="0.000001" name="latitude" value="{{ old('latitude', -3.3275) }}" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('latitude') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Longitude (Desimal)</label>
                        <input type="number" step="0.000001" name="longitude" value="{{ old('longitude', 114.5683) }}" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-slate-200 text-sm focus:ring-1 focus:ring-blue-500 focus:outline-none" />
                        @error('longitude') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-700/50">
                    <a href="{{ route('logistics.locations.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-semibold transition-all">Batal</a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold shadow-lg shadow-blue-500/25 transition-all">Simpan Lokasi</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
