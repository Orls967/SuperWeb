@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="waitlistQueueApp()">
    {{-- Main Container --}}
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                        Waitlist Governor V11
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                        15-Minute Timed Seat Releases
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Tiering Priority Enabled
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Portal Antrean Tiket & Pelepasan Kursi 15 Menit
                </h1>
                <p class="text-sm text-slate-400 mt-1 max-w-3xl">
                    Prioritas ranking otomatis berbobot tier loyalitas (Infinity/Moon/Sun), riwayat pengeluaran tahunan, dan senioritas antrean. Setiap penawaran kursi dibatasi tepat 15 menit sebelum dialihkan otomatis.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('venue.secondary.marketplace') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl border border-slate-700 text-sm">
                    ← Kembali ke Marketplace
                </a>
            </div>
        </div>

        {{-- Success alert --}}
        @if(session('success'))
            <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-xl text-emerald-300 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        {{-- Pending Offers with 15-Minute Countdown Timer --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-black text-white flex items-center gap-2">
                    <span>Penawaran Kursi yang Siap Diklaim</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        WAKTU TERBATAS (15 MENIT)
                    </span>
                </h2>
                <span class="text-xs text-slate-400">Timer berjalan secara real-time</span>
            </div>

            @forelse($pendingOffers as $offer)
                <div class="bg-gradient-to-r from-slate-800 via-slate-800/90 to-amber-950/30 border border-amber-500/40 rounded-2xl p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6 hover:border-amber-500/60 transition-all">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-amber-400">{{ $offer->waitlist_code }}</span>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                OFFERED
                            </span>
                            <span class="text-xs text-slate-400">• Zona: {{ $offer->zone->name ?? 'VIP Deck' }}</span>
                        </div>
                        <h3 class="text-2xl font-black text-white">{{ $offer->event->title ?? 'Bali Sunset Festival 2026' }}</h3>
                        <p class="text-xs text-slate-300 max-w-xl leading-relaxed">
                            Kursi telah dialokasikan khusus untuk Anda berdasarkan skor prioritas antrean. Segera selesaikan klaim sebelum batas waktu habis agar kuota tidak dilepas ke antrean urutan berikutnya!
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-6 w-full md:w-auto justify-between md:justify-end border-t md:border-t-0 pt-4 md:pt-0 border-slate-700">
                        {{-- Live Countdown Display --}}
                        <div class="text-center p-3.5 bg-slate-950/80 rounded-xl border border-amber-500/30 min-w-[140px]">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Sisa Waktu Klaim</div>
                            <div class="text-2xl font-mono font-black text-amber-400 tracking-wider mt-0.5" x-text="formatCountdown({{ $offer->offer_expires_at ? $offer->offer_expires_at->timestamp : time() + 900 }})">
                                14:59
                            </div>
                            <div class="text-[9px] text-slate-500">Auto-release jika lewat</div>
                        </div>

                        <form method="POST" action="{{ route('venue.secondary.waitlist.claim') }}">
                            @csrf
                            <input type="hidden" name="waitlist_id" value="{{ $offer->id }}">
                            <button type="submit" class="px-6 py-3.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-black text-sm rounded-xl shadow-lg transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Klaim Kursi Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8 bg-slate-800/40 border border-slate-800 rounded-2xl text-center text-slate-400 text-sm">
                    <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="font-semibold text-slate-300">Saat ini belum ada penawaran kursi dengan timer aktif untuk akun Anda.</p>
                    <p class="text-xs text-slate-500 mt-1">Kursi yang dibatalkan oleh tamu lain akan langsung ditawarkan ke antrean dengan skor prioritas tertinggi.</p>
                </div>
            @endforelse
        </div>

        {{-- My Active Waitlists --}}
        <div class="space-y-4 pt-4 border-t border-slate-800">
            <h2 class="text-xl font-bold text-white">Status Antrean Waitlist Saya</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($myWaitlists as $wl)
                    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-5 shadow-lg space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-cyan-400">{{ $wl->waitlist_code }}</span>
                            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider
                                  {{ $wl->status === 'CLAIMED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : ($wl->status === 'OFFERED' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-700 text-slate-300') }}">
                                {{ $wl->status }}
                            </span>
                        </div>
                        <h4 class="text-lg font-bold text-white">{{ $wl->event->title ?? 'Event' }}</h4>
                        <div class="text-xs text-slate-400 space-y-1">
                            <div>Zona: <span class="text-slate-200 font-semibold">{{ $wl->zone->name ?? 'General Zone' }}</span></div>
                            <div>Terdaftar: <span class="text-slate-200">{{ $wl->created_at->format('d M Y H:i') }}</span></div>
                        </div>

                        @if($wl->status === 'WAITING')
                            <div class="p-3 bg-slate-900/60 rounded-xl text-xs text-slate-400 border border-slate-800 flex items-center justify-between">
                                <span>Posisi Antrean Berjalan (Menunggu Rilis)</span>
                                <span class="text-cyan-400 font-bold">LIVE QUEUE</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center bg-slate-800/30 border border-slate-800 rounded-2xl">
                        <p class="text-sm text-slate-500">Anda belum mendaftar di antrean waitlist event mana pun.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- How Waitlist Governor Algorithm Works --}}
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Bagaimana Mekanisme Prioritas Waitlist Governor V11 Bekerja?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-400 leading-relaxed">
                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-cyan-400 uppercase tracking-wider">1. Bobot Tier Loyalitas</div>
                    <p>Member tier Infinity mendapat prioritas 100.000 poin, Moon 50.000 poin, dan Sun 25.000 poin untuk memastikan tamu VIP mendapat akses pertama.</p>
                </div>

                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-amber-400 uppercase tracking-wider">2. Akumulasi Pengeluaran 365 Hari</div>
                    <p>Setiap transaksi bernilai Rp 1.000 dalam setahun terakhir memberikan tambahan 1 poin prioritas antrean untuk mengapresiasi loyalitas nyata.</p>
                </div>

                <div class="p-4 bg-slate-900/70 rounded-xl border border-slate-800 space-y-1.5">
                    <div class="font-bold text-emerald-400 uppercase tracking-wider">3. Tenggat 15 Menit Ketat</div>
                    <p>Tamu terpilih diberi waktu tepat 15 menit untuk mengklaim kursi. Jika tidak diambil, kursi secara otomatis dialihkan ke antrean urutan berikutnya.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function waitlistQueueApp() {
    return {
        nowTimestamp: Math.floor(Date.now() / 1000),

        init() {
            setInterval(() => {
                this.nowTimestamp = Math.floor(Date.now() / 1000);
            }, 1000);
        },

        formatCountdown(expireTimestamp) {
            const diff = expireTimestamp - this.nowTimestamp;
            if (diff <= 0) return '00:00 (EXPIRED)';

            const mins = Math.floor(diff / 60);
            const secs = diff % 60;
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }
    };
}
</script>
@endsection

{{-- Interactive Waitlist Ranking Score Simulator --}}
<div class="mt-8 bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 shadow-xl space-y-6" x-data="{ simTier: 'INFINITY', simSpend: 25000000, simSeniorityMins: 30 }">
    <div class="border-b border-slate-700/60 pb-3 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Kalkulator Simulasi Skor Prioritas Antrean (Algoritma V11)
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Uji coba kalkulasi skor deterministik berdasarkan tier loyalitas dan akumulasi belanja Anda.</p>
        </div>
        <span class="text-xs font-mono px-2.5 py-0.5 rounded bg-cyan-500/20 text-cyan-300 font-bold">LIVE ALGORITHM SIM</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
        <div class="space-y-4 text-xs">
            <div>
                <label class="block text-slate-400 font-semibold mb-1">Pilih Tier Loyalitas Tamu:</label>
                <select x-model="simTier" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white">
                    <option value="INFINITY">Infinity Tier (100.000 Poin Dasar)</option>
                    <option value="MOON">Moon Tier (50.000 Poin Dasar)</option>
                    <option value="SUN">Sun Tier (25.000 Poin Dasar)</option>
                    <option value="REGULER">Tamu Umum / Reguler (0 Poin Dasar)</option>
                </select>
            </div>

            <div>
                <div class="flex justify-between text-slate-400 font-semibold mb-1">
                    <span>Pengeluaran 365 Hari:</span>
                    <span class="text-cyan-400 font-mono font-bold" x-text="'Rp ' + Number(simSpend).toLocaleString('id-ID')"></span>
                </div>
                <input type="range" min="0" max="100000000" step="1000000" x-model.number="simSpend" class="w-full accent-cyan-500">
            </div>

            <div>
                <div class="flex justify-between text-slate-400 font-semibold mb-1">
                    <span>Lama Terdaftar di Antrean:</span>
                    <span class="text-amber-400 font-mono font-bold" x-text="simSeniorityMins + ' Menit'"></span>
                </div>
                <input type="range" min="1" max="300" step="5" x-model.number="simSeniorityMins" class="w-full accent-amber-500">
            </div>
        </div>

        <div class="md:col-span-2 p-5 bg-slate-950/90 rounded-xl border border-cyan-500/30 space-y-3">
            <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                <span class="text-xs font-bold text-slate-300 uppercase">Rincian Komponen Skor V11</span>
                <span class="text-xs font-mono text-cyan-400">DETERMINISTIC RANKING</span>
            </div>

            <div class="grid grid-cols-3 gap-3 text-xs">
                <div class="p-3 bg-slate-900 rounded-lg border border-slate-800">
                    <div class="text-[10px] text-slate-500">Poin Tier</div>
                    <div class="text-lg font-bold text-cyan-400 mt-1" x-text="simTier === 'INFINITY' ? '100.000' : (simTier === 'MOON' ? '50.000' : (simTier === 'SUN' ? '25.000' : '0'))"></div>
                </div>
                <div class="p-3 bg-slate-900 rounded-lg border border-slate-800">
                    <div class="text-[10px] text-slate-500">Poin Belanja (1 pt / 1k IDR)</div>
                    <div class="text-lg font-bold text-emerald-400 mt-1" x-text="Number(Math.floor(simSpend / 1000)).toLocaleString('id-ID')"></div>
                </div>
                <div class="p-3 bg-slate-900 rounded-lg border border-slate-800">
                    <div class="text-[10px] text-slate-500">Poin Senioritas (1 pt / 10s)</div>
                    <div class="text-lg font-bold text-amber-400 mt-1" x-text="Number(Math.floor((simSeniorityMins * 60) / 10)).toLocaleString('id-ID')"></div>
                </div>
            </div>

            <div class="p-3 bg-cyan-950/40 rounded-xl border border-cyan-500/40 flex justify-between items-center">
                <span class="text-xs font-bold text-cyan-300 uppercase tracking-wider">Total Skor Prioritas:</span>
                <span class="text-2xl font-black text-white font-mono"
                      x-text="Number((simTier === 'INFINITY' ? 100000 : (simTier === 'MOON' ? 50000 : (simTier === 'SUN' ? 25000 : 0))) + Math.floor(simSpend / 1000) + Math.floor((simSeniorityMins * 60) / 10)).toLocaleString('id-ID')">
                </span>
            </div>
        </div>
    </div>
</div>
