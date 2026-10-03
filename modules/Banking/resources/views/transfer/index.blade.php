@extends('layouts.app')

@section('title', 'Transfer Saldo')
@section('subtitle', 'Kirim Dana Antar Pengguna dengan Cepat & Aman')

@section('content')
<div class="max-w-2xl mx-auto space-y-6"
     x-data="{
        step: 1,
        recipientQuery: '{{ old('recipient_query', '') }}',
        recipientName: '',
        recipientEmail: '',
        recipientAccount: '',
        recipientFound: false,
        searching: false,
        searchError: '',
        amount: '{{ old('amount', '') }}',
        note: '{{ old('note', '') }}',
        pin: '',
        currentBalance: {{ (float) $idrBalance->amount->__toString() }},
        get fee() {
            let val = parseFloat(this.amount) || 0;
            return val > 1000000 ? 2500 : 0;
        },
        get totalDeduction() {
            let val = parseFloat(this.amount) || 0;
            return val + this.fee;
        },
        get canAfford() {
            return this.totalDeduction > 0 && this.totalDeduction <= this.currentBalance;
        },
        async lookupRecipient() {
            if (!this.recipientQuery.trim()) {
                this.searchError = 'Ketik email atau nomor akun tujuan.';
                return;
            }
            this.searching = true;
            this.searchError = '';
            try {
                let res = await fetch('{{ route('wallet.transfer.lookup') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ query: this.recipientQuery })
                });
                let data = await res.json();
                if (data.found) {
                    this.recipientName = data.name;
                    this.recipientEmail = data.email;
                    this.recipientAccount = data.account_code;
                    this.recipientFound = true;
                    this.searchError = '';
                } else {
                    this.recipientFound = false;
                    this.searchError = data.message || 'Penerima tidak ditemukan.';
                }
            } catch (err) {
                this.searchError = 'Gagal menghubungi server.';
            } finally {
                this.searching = false;
            }
        },
        formatRupiah(num) {
            return 'Rp ' + (new Intl.NumberFormat('id-ID').format(num || 0));
        }
     }">

    {{-- Error Flash --}}
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Wizard Step Indicator --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-5 backdrop-blur-xl">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs"
                     :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-400'">
                    1
                </div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 1 ? 'text-white' : 'text-slate-400'">
                    Tujuan Transfer
                </span>
            </div>

            <div class="flex-1 mx-4 h-0.5" :class="step >= 2 ? 'bg-indigo-600' : 'bg-slate-700'"></div>

            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs"
                     :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-400'">
                    2
                </div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 2 ? 'text-white' : 'text-slate-400'">
                    Nominal & Biaya
                </span>
            </div>

            <div class="flex-1 mx-4 h-0.5" :class="step >= 3 ? 'bg-indigo-600' : 'bg-slate-700'"></div>

            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs"
                     :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-slate-400'">
                    3
                </div>
                <span class="text-xs font-semibold hidden sm:inline" :class="step >= 3 ? 'text-white' : 'text-slate-400'">
                    Konfirmasi PIN
                </span>
            </div>
        </div>
    </div>

    {{-- Form Container --}}
    <div class="rounded-2xl bg-slate-800/80 border border-slate-700/60 p-6 backdrop-blur-xl shadow-2xl">
        <form method="POST" action="{{ route('wallet.transfer.store') }}">
            @csrf

            <input type="hidden" name="recipient_query" :value="recipientQuery">
            <input type="hidden" name="amount" :value="amount">
            <input type="hidden" name="note" :value="note">
            {{-- Kunci idempoten tetap sama saat submit ulang setelah validasi gagal --}}
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

            {{-- STEP 1: TUJUAN TRANSFER --}}
            <div x-show="step === 1" class="space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-white">Langkah 1: Tentukan Penerima</h3>
                    <p class="text-xs text-slate-400 mt-1">Cari penerima berdasarkan alamat email terdaftar atau kode nomor akun dompet.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-2">Email atau Nomor Akun Penerima</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            x-model="recipientQuery"
                            @keydown.enter.prevent="lookupRecipient()"
                            placeholder="Contoh: user@example.com atau wallet:user:2:IDR"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 font-mono">
                        <button
                            type="button"
                            @click="lookupRecipient()"
                            :disabled="searching"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition-all disabled:opacity-50 flex items-center gap-2">
                            <span x-show="!searching">Cari</span>
                            <span x-show="searching">Mencari...</span>
                        </button>
                    </div>
                    <p x-show="searchError" x-text="searchError" class="text-xs text-rose-400 mt-2"></p>
                </div>

                {{-- Recipient Preview Card --}}
                <div x-show="recipientFound" x-transition class="p-4 rounded-xl bg-slate-900/60 border border-indigo-500/40 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                            ✓
                        </div>
                        <div>
                            <p class="text-xs text-indigo-400 font-medium uppercase tracking-wider">Penerima Ditemukan</p>
                            <h4 class="text-base font-bold text-white tracking-wide" x-text="recipientName"></h4>
                            <p class="text-xs text-slate-400 font-mono" x-text="recipientAccount"></p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-700/60 flex justify-between items-center">
                    <a href="{{ route('wallet.index') }}" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-600">
                        Batal
                    </a>
                    <button
                        type="button"
                        @click="if (recipientFound) step = 2;"
                        :disabled="!recipientFound"
                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-all disabled:opacity-40">
                        Lanjut ke Nominal &rarr;
                    </button>
                </div>
            </div>

            {{-- STEP 2: NOMINAL & BIAYA --}}
            <div x-show="step === 2" class="space-y-6" x-cloak>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-white">Langkah 2: Tentukan Nominal</h3>
                        <p class="text-xs text-slate-400 mt-1">Penerima: <span class="text-indigo-400 font-semibold" x-text="recipientName"></span></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] text-slate-400">Saldo Anda Saat Ini</p>
                        <p class="text-sm font-bold text-emerald-400 font-mono">{{ $idrBalance->format() }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-2">Pilih Nominal Cepat</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([50000 => '50 Rb', 100000 => '100 Rb', 500000 => '500 Rb', 1000000 => '1 Jt', 2000000 => '2 Jt', 5000000 => '5 Jt'] as $v => $l)
                            <button
                                type="button"
                                @click="amount = '{{ $v }}'"
                                :class="amount == '{{ $v }}' ? 'border-indigo-500 bg-indigo-500/20 text-indigo-300 font-bold' : 'border-slate-700 bg-slate-900/50 text-slate-300'"
                                class="py-2 px-3 rounded-xl border text-xs text-center transition-all hover:border-slate-600">
                                {{ $l }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="transfer_amount" class="block text-xs font-medium text-slate-300 mb-1">Nominal Transfer (Rp)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm font-bold">Rp</span>
                        <input
                            type="number"
                            id="transfer_amount"
                            x-model="amount"
                            min="1000"
                            step="1000"
                            required
                            placeholder="0"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white font-mono text-base focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="transfer_note" class="block text-xs font-medium text-slate-300 mb-1">Catatan Transaksi (Opsional)</label>
                    <input
                        type="text"
                        id="transfer_note"
                        x-model="note"
                        maxlength="100"
                        placeholder="Contoh: Uang servis mobil / Pembelian sparepart"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-900/80 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                {{-- Fee Breakdown Box --}}
                <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-300">
                        <span>Nominal Transfer</span>
                        <span class="font-mono font-medium" x-text="formatRupiah(amount)"></span>
                    </div>
                    <div class="flex justify-between text-slate-300">
                        <span>Biaya Admin Sistem</span>
                        <span class="font-mono font-medium" :class="fee > 0 ? 'text-amber-400' : 'text-emerald-400'"
                              x-text="fee > 0 ? formatRupiah(fee) : 'Gratis (Nominal ≤ Rp 1.000.000)'"></span>
                    </div>
                    <div class="pt-2 border-t border-slate-700/60 flex justify-between font-bold text-sm text-white">
                        <span>Total Pengurangan Saldo</span>
                        <span class="font-mono text-indigo-400" x-text="formatRupiah(totalDeduction)"></span>
                    </div>
                </div>

                <div x-show="!canAfford && totalDeduction > 0" class="p-3 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs">
                    ⚠️ Saldo tidak mencukupi untuk nominal transfer dan biaya admin ini.
                </div>

                <div class="pt-4 border-t border-slate-700/60 flex justify-between items-center">
                    <button
                        type="button"
                        @click="step = 1"
                        class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-600">
                        &larr; Kembali
                    </button>
                    <button
                        type="button"
                        @click="if (canAfford) step = 3;"
                        :disabled="!canAfford"
                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-all disabled:opacity-40">
                        Lanjut ke Konfirmasi &rarr;
                    </button>
                </div>
            </div>

            {{-- STEP 3: KONFIRMASI & INPUT PIN --}}
            <div x-show="step === 3" class="space-y-6" x-cloak>
                <div>
                    <h3 class="text-lg font-bold text-white">Langkah 3: Konfirmasi & Input PIN</h3>
                    <p class="text-xs text-slate-400 mt-1">Periksa kembali detail transaksi Anda lalu masukkan 6-digit PIN untuk otorisasi.</p>
                </div>

                <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-900/40 to-slate-900 border border-indigo-500/30 space-y-3">
                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Penerima</span>
                        <span class="font-bold text-white" x-text="recipientName"></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Nomor Rekening Tujuan</span>
                        <span class="font-mono text-slate-400" x-text="recipientAccount"></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Nominal</span>
                        <span class="font-mono font-bold text-white text-sm" x-text="formatRupiah(amount)"></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-300">
                        <span>Biaya Admin</span>
                        <span class="font-mono" x-text="fee > 0 ? formatRupiah(fee) : 'Rp 0 (Gratis)'"></span>
                    </div>
                    <div x-show="note" class="flex justify-between text-xs text-slate-300">
                        <span>Catatan</span>
                        <span class="text-slate-400 italic" x-text="note"></span>
                    </div>
                    <div class="pt-3 border-t border-slate-700/60 flex justify-between font-bold text-base text-white">
                        <span>Total Potongan</span>
                        <span class="font-mono text-emerald-400" x-text="formatRupiah(totalDeduction)"></span>
                    </div>
                </div>

                @if(!$hasPin)
                    <div class="p-4 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs flex items-center justify-between">
                        <span>Anda belum mengatur PIN transaksi 6-digit.</span>
                        <a href="{{ route('wallet.index') }}" class="underline font-bold">Atur di Dompet</a>
                    </div>
                @else
                    <div>
                        <label for="transfer_pin" class="block text-xs font-medium text-slate-300 mb-2 text-center">
                            Masukkan 6 Digit PIN Transaksi Anda
                        </label>
                        <div class="max-w-xs mx-auto">
                            <input
                                type="password"
                                id="transfer_pin"
                                name="pin"
                                x-model="pin"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                required
                                autocomplete="off"
                                placeholder="••••••"
                                class="w-full px-4 py-3 rounded-xl bg-slate-900/90 border border-indigo-500/50 text-white font-mono text-center text-2xl tracking-[0.5em] focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-700/60 flex justify-between items-center">
                    <button
                        type="button"
                        @click="step = 2"
                        class="px-4 py-2 rounded-xl bg-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-600">
                        &larr; Ubah Nominal
                    </button>
                    <button
                        type="submit"
                        :disabled="pin.length !== 6 || !hasPin"
                        class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold shadow-lg shadow-emerald-600/30 transition-all disabled:opacity-40 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Konfirmasi & Kirim Transfer
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection
