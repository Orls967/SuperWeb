@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="artistStatementDashboard()">
    {{-- P-UI 5: Toast Notifications --}}
    <div x-show="toast.visible" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-5 right-5 z-50 flex items-center p-4 rounded-xl shadow-2xl border"
         :class="toast.type === 'success' ? 'bg-emerald-950 border-emerald-500 text-emerald-200' : 'bg-rose-950 border-rose-500 text-rose-200'"
         style="display: none;">
        <span class="mr-3 text-lg" x-text="toast.type === 'success' ? '✅' : '⚠️'"></span>
        <div>
            <p class="font-semibold text-sm" x-text="toast.title"></p>
            <p class="text-xs opacity-90" x-text="toast.message"></p>
        </div>
    </div>

    {{-- Header & Event Selector --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-purple-500/20 text-purple-400 border border-purple-500/30">
                        Artist Deal Settlement V5
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        HMAC Certificate Signed
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Lembar Penyelesaian Kontrak Artis & Rider Audit
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Kompensasi versus deal (Guarantee vs % Gross Pintu), amortisasi advance, pemotongan PPh 21/26, dan konversi valuta asing (D90-1).
                </p>
            </div>

            {{-- P-UI 9: Filter Acara --}}
            <form method="GET" action="{{ route('venue.economy.artists') }}" class="flex items-center gap-3">
                <select name="event_id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-purple-500 focus:outline-none">
                    @foreach($events as $ev)
                        <option value="{{ $ev->id }}" {{ $ev->id === $selectedEventId ? 'selected' : '' }}>
                            {{ $ev->title }} ({{ $ev->event_code }})
                        </option>
                    @endforeach
                </select>
                <button type="button" @click="openSettleModal = true" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-purple-600/30 transition">
                    + Settlement Artis Baru
                </button>
            </form>
        </div>
    </div>

    {{-- Main Container --}}
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/50 text-emerald-200 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">✕</button>
            </div>
        @endif

        {{-- Table List of Artist Engagements --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>Daftar Kontrak & Rekonsiliasi Artis</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $engagements->total() }} Kontrak
                    </span>
                </h2>
            </div>

            {{-- P-UI 2: Empty State --}}
            @if($engagements->isEmpty())
                <div class="text-center py-16 px-4">
                    <div class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-500 border border-slate-700">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-300">Belum Ada Kontrak Artis untuk Acara Ini</h3>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        Gunakan tombol di atas untuk membuat penyelesaian kontrak artis dengan algoritma V5 ArtistDoorSplitCalculator.
                    </p>
                    <button type="button" @click="openSettleModal = true" class="mt-4 px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-semibold transition">
                        Buka Form Settlement
                    </button>
                </div>
            @else
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                            <tr>
                                <th scope="col" class="py-3 px-4">No. Kontrak</th>
                                <th scope="col" class="py-3 px-4">Nama Artis & Manajemen</th>
                                <th scope="col" class="py-3 px-4">Tipe Deal</th>
                                <th scope="col" class="py-3 px-4">Jaminan vs Porsi</th>
                                <th scope="col" class="py-3 px-4">Gross Pay</th>
                                <th scope="col" class="py-3 px-4">Advance Paid</th>
                                <th scope="col" class="py-3 px-4">WHT (PPh)</th>
                                <th scope="col" class="py-3 px-4">Net Transfer</th>
                                <th scope="col" class="py-3 px-4">Valas Cents</th>
                                <th scope="col" class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($engagements as $eng)
                                <tr class="hover:bg-slate-750/50 transition">
                                    <td class="py-3.5 px-4 font-mono text-xs text-purple-300">
                                        {{ $eng->contract_number }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-white">{{ $eng->artist_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $eng->management_company ?? 'Independen' }}</div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-700 text-amber-300 uppercase">
                                            {{ $eng->deal_type }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs text-slate-400">
                                        Guar: IDR {{ number_format($eng->guarantee_idr) }}<br>
                                        Share: {{ $eng->door_share_bps / 100 }}%
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-white">
                                        IDR {{ number_format($eng->gross_pay_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-rose-400">
                                        IDR {{ number_format($eng->advance_paid_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-amber-400">
                                        IDR {{ number_format($eng->wht_idr) }} ({{ $eng->wht_bps / 100 }}%)
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-emerald-400">
                                        IDR {{ number_format($eng->net_paid_idr) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-cyan-300">
                                        @if($eng->valas_minor_cents > 0)
                                            ${{ number_format($eng->valas_minor_cents / 100, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        {{-- P-UI 8: Active Indicator --}}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-500/10 text-purple-300 border border-purple-500/30">
                                            ● {{ ucfirst($eng->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700/60">
                    {{ $engagements->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Audit Rider Teknis & Hospitality (D90-1 Perluasan) --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-700/60 gap-4">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Audit Rider Teknis & Hospitality Artis</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-purple-500/20 text-purple-300 font-semibold">
                            Tracking Realisasi Belanja
                        </span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Verifikasi pemenuhan rider sound system, backline panggung, akomodasi suite, pengawalan, dan pemotongan clawback deposit bila rider wanprestasi.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Total Anggaran Rider: IDR 45.000.000
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                {{-- Rider 1: Technical Sound & Lighting --}}
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-cyan-500/20 text-cyan-300">Technical Rider</span>
                        <span class="text-xs font-semibold text-emerald-400">✓ Terpenuhi</span>
                    </div>
                    <h4 class="text-sm font-bold text-white">Pioneer DJ CDJ-3000 + DJM-V10 Mixer</h4>
                    <p class="text-xs text-slate-400">Monitoring booth L-Acoustics 115XT HiQ dual wedges dengan attenuator 120dB.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between text-xs font-mono">
                        <span class="text-slate-400">Realisasi / Anggaran:</span>
                        <span class="text-slate-200">IDR 18.500.000 / 20.000.000</span>
                    </div>
                </div>

                {{-- Rider 2: Hospitality & Green Room --}}
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-300">Hospitality Rider</span>
                        <span class="text-xs font-semibold text-emerald-400">✓ Terpenuhi</span>
                    </div>
                    <h4 class="text-sm font-bold text-white">Dom Pérignon Luminous & Air Kelapa Muda</h4>
                    <p class="text-xs text-slate-400">3 Botol Champagne Vintage, buah organik tropis Bali, cold towels, dan vegan snack basket.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between text-xs font-mono">
                        <span class="text-slate-400">Realisasi / Anggaran:</span>
                        <span class="text-slate-200">IDR 16.200.000 / 15.000.000</span>
                    </div>
                </div>

                {{-- Rider 3: Transport & VIP Security --}}
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-500/20 text-purple-300">Transport & Guard</span>
                        <span class="text-xs font-semibold text-emerald-400">✓ Terpenuhi</span>
                    </div>
                    <h4 class="text-sm font-bold text-white">Toyota Alphard Escort + 4 Bodyguards</h4>
                    <p class="text-xs text-slate-400">Antar jemput bandara Ngurah Rai, jalur khusus gerbang belakang panggung, dan kartu akses zona steril.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between text-xs font-mono">
                        <span class="text-slate-400">Realisasi / Anggaran:</span>
                        <span class="text-slate-200">IDR 9.500.000 / 10.000.000</span>
                    </div>
                </div>
            </div>

            {{-- Klausul Clawback & Pinalti Pembatalan --}}
            <div class="mt-6 p-4 rounded-xl bg-purple-950/30 border border-purple-500/30 flex items-start gap-3">
                <span class="text-lg">⚖️</span>
                <div class="text-xs text-slate-300">
                    <span class="font-bold text-purple-300">Protokol Escrow & Penalti Wanprestasi:</span>
                    Bila artis membatalkan penampilan sepihak kurang dari 14 hari sebelum acara, uang muka (advance paid) wajib dikembalikan penuh ditambah biaya kompensasi 25% dari nilai garansi. Bila venue gagal menyediakan rider teknis esensial, artis berhak menolak tampil tanpa penalti.
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Modal Input Settlement Artis V5 --}}
    <div x-show="openSettleModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openSettleModal = false"></div>
            <div class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Penyelesaian Kontrak Artis (Algoritma V5)</h3>
                        <p class="text-xs text-slate-400">Verifikasi formula versus deal dan pembuatan entri buku besar otomatis.</p>
                    </div>
                    <button type="button" @click="openSettleModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form method="POST" action="{{ route('venue.economy.artists.settle') }}" class="space-y-4 py-4">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $selectedEventId }}">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Nama Artis / DJ</label>
                            <input type="text" name="artist_name" x-model="form.artist_name" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Manajemen / Agency</label>
                            <input type="text" name="management_company" x-model="form.management_company" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Tipe Kesepakatan</label>
                            <select name="deal_type" x-model="form.deal_type" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                                <option value="versus">Versus Deal (Max)</option>
                                <option value="guarantee">Guarantee Saja</option>
                                <option value="door_split">Door Split Saja</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Jaminan Minimal (IDR)</label>
                            <input type="number" name="guarantee_idr" x-model="form.guarantee_idr" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Porsi Bagi Hasil (bps)</label>
                            <input type="number" name="share_bps" x-model="form.share_bps" required placeholder="3000 = 30%" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0" max="10000">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Gross Penjualan Pintu (IDR)</label>
                            <input type="number" name="door_gross_idr" x-model="form.door_gross_idr" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Beban Acara Disetujui (IDR)</label>
                            <input type="number" name="approved_expenses_idr" x-model="form.approved_expenses_idr" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Uang Muka / Advance (IDR)</label>
                            <input type="number" name="advance_paid_idr" x-model="form.advance_paid_idr" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Tarif Pajak PPh (bps)</label>
                            <input type="number" name="wht_bps" x-model="form.wht_bps" required placeholder="2000 = 20%" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0" max="10000">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Mata Uang & Kurs Spot</label>
                            <div class="flex gap-2 mt-1">
                                <select name="currency" x-model="form.currency" class="bg-slate-800 border border-slate-700 rounded-xl px-2 py-2 text-sm text-white">
                                    <option value="IDR">IDR</option>
                                    <option value="USD">USD</option>
                                    <option value="SGD">SGD</option>
                                </select>
                                <input type="number" name="exchange_rate_idr" x-model="form.exchange_rate_idr" placeholder="16250" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-2 py-2 text-sm text-white">
                            </div>
                        </div>
                    </div>

                    {{-- Live Calculation Box --}}
                    <div class="p-4 bg-slate-800/80 rounded-xl border border-purple-500/40 space-y-2">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Laba Bersih Pintu (Door Net):</span>
                            <span class="font-bold text-white" x-text="'IDR ' + Number(doorNet()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Porsi Pintu Terhitung (Door Share):</span>
                            <span class="font-bold text-white" x-text="'IDR ' + Number(doorShare()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Hak Kotor (Gross Pay):</span>
                            <span class="font-bold text-purple-400" x-text="'IDR ' + Number(grossPay()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Potongan Pajak PPh (WHT):</span>
                            <span class="font-bold text-rose-400" x-text="'IDR ' + Number(whtAmount()).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-slate-700">
                            <span>Pelunasan Neto IDR:</span>
                            <span class="text-emerald-400" x-text="'IDR ' + Number(netPayable()).toLocaleString('id-ID')"></span>
                        </div>
                        <div x-show="form.currency !== 'IDR'" class="flex justify-between text-xs text-cyan-300">
                            <span>Ekuivalen Valas (Cents):</span>
                            <span class="font-bold" x-text="'$' + (valasCents() / 100).toFixed(2) + ' USD'"></span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="openSettleModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-purple-600/30">
                            Simpan & Posting Buku Besar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function artistStatementDashboard() {
    return {
        openSettleModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        form: {
            artist_name: 'DJ Coastliner',
            management_company: 'SoundWave Global Management',
            deal_type: 'versus',
            guarantee_idr: 100000000,
            door_gross_idr: 600000000,
            approved_expenses_idr: 150000000,
            share_bps: 3000,
            wht_bps: 2000,
            advance_paid_idr: 50000000,
            currency: 'USD',
            exchange_rate_idr: 16250
        },
        doorNet() {
            return Math.max(0, Number(this.form.door_gross_idr || 0) - Number(this.form.approved_expenses_idr || 0));
        },
        doorShare() {
            return Math.floor((this.doorNet() * Number(this.form.share_bps || 0)) / 10000);
        },
        grossPay() {
            const guar = Number(this.form.guarantee_idr || 0);
            const share = this.doorShare();
            if (this.form.deal_type === 'guarantee') return guar;
            if (this.form.deal_type === 'door_split') return share;
            return Math.max(guar, share);
        },
        whtAmount() {
            return Math.floor((this.grossPay() * Number(this.form.wht_bps || 0)) / 10000);
        },
        netPayable() {
            return Math.max(0, this.grossPay() - this.whtAmount() - Number(this.form.advance_paid_idr || 0));
        },
        valasCents() {
            const rate = Number(this.form.exchange_rate_idr || 1);
            if (rate <= 0) return 0;
            return Math.floor((this.netPayable() * 100) / rate);
        }
    };
}
</script>
@endsection
