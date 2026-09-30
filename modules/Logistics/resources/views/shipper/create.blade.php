<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('logistics.shipments.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="font-bold text-2xl text-white tracking-tight">Kirim Kargo Baru</h2>
                <p class="text-sm text-slate-400">Pesan pengiriman barang multi-moda dengan tarif terstandarisasi dan pelacakan real-time</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        packages: [
            { weight_g: 1000, length_mm: 200, width_mm: 150, height_mm: 100, description: 'Barang Kiriman' }
        ],
        paymentTerms: '{{ $account ? 'postpaid' : 'prepaid' }}',
        insured: false,
        addPackage() {
            this.packages.push({ weight_g: 1000, length_mm: 200, width_mm: 150, height_mm: 100, description: 'Paket #' + (this.packages.length + 1) });
        },
        removePackage(index) {
            if (this.packages.length > 1) {
                this.packages.splice(index, 1);
            }
        }
    }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-900/40 border border-rose-500/40 text-rose-300 text-sm">
                    <div class="font-semibold mb-1">Periksa kembali formulir isian:</div>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('logistics.shipments.store') }}" class="space-y-6">
                @csrf

                <!-- Section 1: Rute & Moda -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                    <h3 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs">1</span>
                        Rute & Level Layanan
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Lokasi Asal (Origin)</label>
                            <select name="origin_location_id" required class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <option value="">Pilih Hub / Titik Asal</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ old('origin_location_id') == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->name }} ({{ $loc->code }}) — {{ $loc->city }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Lokasi Tujuan (Destination)</label>
                            <select name="destination_location_id" required class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <option value="">Pilih Hub / Titik Tujuan</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ old('destination_location_id') == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->name }} ({{ $loc->code }}) — {{ $loc->city }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Pilihan Layanan Kargo</label>
                            <select name="service_level" required class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                @foreach(\Modules\Logistics\Domain\Enums\ServiceLevel::cases() as $sl)
                                    <option value="{{ $sl->value }}" {{ old('service_level') === $sl->value ? 'selected' : '' }}>
                                        {{ $sl->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Informasi Penerima (Consignee) -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                    <h3 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs">2</span>
                        Data Penerima (Consignee)
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Lengkap Penerima</label>
                            <input type="text" name="consignee_name" value="{{ old('consignee_name') }}" required placeholder="Contoh: Ahmad Yani" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">No. Telepon / WhatsApp</label>
                            <input type="text" name="consignee_phone" value="{{ old('consignee_phone') }}" required placeholder="0812xxxxxxxx" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Alamat Lengkap</label>
                            <textarea name="consignee_street" rows="2" required placeholder="Jl. Raya No. ..., RT/RW, Kelurahan, Kecamatan" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">{{ old('consignee_street') }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kota / Kabupaten</label>
                            <input type="text" name="consignee_city" value="{{ old('consignee_city') }}" required placeholder="Contoh: Banjarbaru" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Kode Pos</label>
                            <input type="text" name="consignee_postal_code" value="{{ old('consignee_postal_code') }}" placeholder="70xxx" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Rincian Kargo & Multi-Paket (Alpine) -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs">3</span>
                            Daftar Kargo / Paket Kiriman
                        </h3>
                        <button type="button" @click="addPackage()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-sky-400 text-xs font-semibold transition border border-slate-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Tambah Paket
                        </button>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(pkg, idx) in packages" :key="idx">
                            <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800/80 space-y-3 relative">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400" x-text="'Paket #' + (idx + 1)"></span>
                                    <button type="button" x-show="packages.length > 1" @click="removePackage(idx)" class="text-xs text-rose-400 hover:text-rose-300">
                                        Hapus
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                                    <div class="sm:col-span-2 md:col-span-2">
                                        <label class="block text-xs text-slate-400 mb-1">Deskripsi Isi</label>
                                        <input type="text" :name="'packages['+idx+'][description]'" x-model="pkg.description" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-400 mb-1">Berat (gram)</label>
                                        <input type="number" :name="'packages['+idx+'][weight_g]'" x-model.number="pkg.weight_g" min="10" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 font-mono">
                                    </div>
                                    <div class="sm:col-span-2 md:col-span-2 grid grid-cols-3 gap-1.5">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">P (mm)</label>
                                            <input type="number" :name="'packages['+idx+'][length_mm]'" x-model.number="pkg.length_mm" min="10" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">L (mm)</label>
                                            <input type="number" :name="'packages['+idx+'][width_mm]'" x-model.number="pkg.width_mm" min="10" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">T (mm)</label>
                                            <input type="number" :name="'packages['+idx+'][height_mm]'" x-model.number="pkg.height_mm" min="10" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 font-mono">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Additional Options -->
                    <div class="mt-4 pt-4 border-t border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nilai Barang Dideklarasikan (IDR)</label>
                            <input type="number" name="declared_value_idr" value="0" min="0" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 font-mono">
                            <label class="mt-2 flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                                <input type="checkbox" name="insured" value="1" x-model="insured" class="rounded bg-slate-800 border-slate-700 text-sky-600 focus:ring-sky-500">
                                Sertakan Asuransi Kargo (0.2%, min Rp 10.000)
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nilai COD (Jika Ada, IDR)</label>
                            <input type="number" name="cod_amount_idr" value="0" min="0" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 font-mono">
                            <p class="text-xs text-slate-500 mt-1">Biaya penanganan COD 3% dipotong saat settlement</p>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Pembayaran & Konfirmasi -->
                <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                    <h3 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs">4</span>
                        Metode Pembayaran
                    </h3>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="p-4 rounded-xl border flex items-start gap-3 cursor-pointer transition" :class="paymentTerms === 'prepaid' ? 'bg-sky-950/40 border-sky-600' : 'bg-slate-950/40 border-slate-800 hover:border-slate-700'">
                                <input type="radio" name="payment_terms" value="prepaid" x-model="paymentTerms" class="mt-1 text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="font-semibold text-white text-sm">Prabayar (Dompet Digital)</div>
                                    <div class="text-xs text-slate-400 mt-0.5">Ongkir dipotong langsung dari saldo dompet Anda</div>
                                </div>
                            </label>

                            <label class="p-4 rounded-xl border flex items-start gap-3 cursor-pointer transition {{ $account ? '' : 'opacity-50 cursor-not-allowed' }}" :class="paymentTerms === 'postpaid' ? 'bg-sky-950/40 border-sky-600' : 'bg-slate-950/40 border-slate-800 hover:border-slate-700'">
                                <input type="radio" name="payment_terms" value="postpaid" x-model="paymentTerms" {{ $account ? '' : 'disabled' }} class="mt-1 text-sky-600 focus:ring-sky-500">
                                <div>
                                    <div class="font-semibold text-white text-sm">Pascabayar (B2B Postpaid)</div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        @if($account)
                                            Ditagihkan pada akhir bulan (Sisa limit: Rp {{ number_format($account->credit_limit_idr - $account->calculateOutstandingBalance(), 0, ',', '.') }})
                                        @else
                                            Hanya untuk akun bisnis yang telah disetujui
                                        @endif
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- PIN input if prepaid -->
                        <div x-show="paymentTerms === 'prepaid'" class="max-w-xs">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">6-Digit PIN Dompet</label>
                            <input type="password" name="pin" maxlength="6" placeholder="******" class="w-full px-3.5 py-2.5 bg-slate-950/70 border border-slate-700/80 rounded-xl text-sm text-slate-200 tracking-widest text-center font-mono focus:outline-none focus:ring-2 focus:ring-sky-500">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('logistics.shipments.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-400 hover:text-white text-sm font-semibold transition">
                        Batal
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white text-sm font-semibold shadow-lg shadow-sky-600/30 transition">
                        Konfirmasi & Pesan Kargo
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
