@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-slate-900 text-slate-100 py-8 px-4 sm:px-6 lg:px-8" x-data="memberProfileDashboard()">
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

    {{-- Header --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold tracking-wider uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        VIP Membership & Locker Storage
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        Tier Engine V7 Active
                    </span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">
                    Portal Keanggotaan & Loker Botol VIP
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Evaluasi kualifikasi 365 hari bergulir, masa tenggang grace period, dan hak akses VIP line bypass (D90-4).
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="openEvaluateModal = true" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-sm shadow-lg shadow-amber-500/20 transition">
                    ⚙️ Evaluasi Tingkat Member (V7)
                </button>
            </div>
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

        {{-- Membership Card Showcase (Active User / Highlight) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Luxury Digital Membership Card --}}
            <div class="lg:col-span-1">
                <div class="relative overflow-hidden rounded-3xl p-6 bg-gradient-to-br from-slate-800 via-slate-850 to-slate-950 border border-amber-500/40 shadow-2xl group">
                    <div class="absolute -right-8 -top-8 w-40 h-40 bg-amber-500/10 rounded-full blur-2xl"></div>
                    <div class="flex items-center justify-between pb-6 border-b border-slate-700/50">
                        <div>
                            <p class="text-xs font-mono text-amber-400 tracking-widest uppercase">Atlas Beach Club</p>
                            <h3 class="text-xl font-black text-white mt-0.5">VIP PASS</h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center font-bold text-amber-400">
                            ★
                        </div>
                    </div>

                    <div class="py-6 space-y-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase">Tingkat Keanggotaan</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-2xl font-black uppercase text-amber-400 tracking-wider">
                                    {{ $membership?->tier ?? 'INFINITY' }}
                                </span>
                                @if(($membership?->tier ?? 'infinity') === 'infinity')
                                    <span class="px-2 py-0.5 text-xs rounded bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                        Founders Tier
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div>
                                <p class="text-xs text-slate-400">Nomor Anggota</p>
                                <p class="text-sm font-mono font-bold text-white mt-0.5">
                                    {{ $membership?->membership_number ?? 'MBR-ATLAS-889' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Loker Botol VIP</p>
                                <p class="text-sm font-mono font-bold text-cyan-400 mt-0.5">
                                    {{ $membership?->bottle_locker_number ?? 'LCK-VIP-08' }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-2">
                            <p class="text-xs text-slate-400">Poin Loyalitas (Lifestyle Points)</p>
                            <p class="text-xl font-extrabold text-emerald-400 mt-0.5">
                                {{ number_format($membership?->loyalty_points ?? 12500) }} PTS
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-700/50 flex items-center justify-between text-xs text-slate-400">
                        <span>Berlaku Hingga:</span>
                        <span class="font-mono text-slate-200">
                            {{ $membership?->expires_at?->format('d M Y') ?? '31 Des 2027' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Benefits & Status Overview --}}
            <div class="lg:col-span-2 bg-slate-800/50 rounded-3xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">Privilese & Metrik Kualifikasi 365 Hari</h3>
                    {{-- P-UI 8: Status Indicator --}}
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        Status: Aktif & Terverifikasi
                    </span>
                </div>

                {{-- Progress Bar Kualifikasi --}}
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-xs font-medium text-slate-300 mb-1">
                            <span>Akumulasi Belanja 365 Hari: IDR {{ number_format($membership?->spend_365_days_idr ?? 82000000) }}</span>
                            <span class="text-amber-400">Ambang Infinity: IDR 75.000.000</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-medium text-slate-300 mb-1">
                            <span>Frekuensi Kunjungan Gerbang: {{ $membership?->visits_365_days ?? 26 }} Kunjungan</span>
                            <span class="text-cyan-400">Syarat Infinity: 24 Kunjungan</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <div class="bg-cyan-400 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                {{-- VIP Privileges Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-700/60">
                    <div class="p-4 rounded-2xl bg-slate-800/80 border border-slate-700">
                        <span class="text-lg">⚡</span>
                        <h4 class="text-sm font-bold text-white mt-1">VIP Fast Track</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Bypass antrean umum di seluruh gerbang turnstile acara.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-800/80 border border-slate-700">
                        <span class="text-lg">🍾</span>
                        <h4 class="text-sm font-bold text-white mt-1">Loker Botol Premium</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Penyimpanan botol spirit belum habis dengan nomor loker tetap.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-800/80 border border-slate-700">
                        <span class="text-lg">🎁</span>
                        <h4 class="text-sm font-bold text-white mt-1">Multiplier Poin 2.0x</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Perolehan poin ganda setiap transaksi F&B dan tiket.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- All Memberships Directory Table --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span>Daftar Anggota & Loker Botol Terdaftar</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300 font-normal">
                        {{ $allMemberships->total() }} Anggota
                    </span>
                </h3>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/60 text-slate-400 border-b border-slate-700/50">
                        <tr>
                            <th scope="col" class="py-3 px-4">No. Member</th>
                            <th scope="col" class="py-3 px-4">User ID</th>
                            <th scope="col" class="py-3 px-4">Tingkat Tier</th>
                            <th scope="col" class="py-3 px-4">Belanja 365 Hari</th>
                            <th scope="col" class="py-3 px-4">Kunjungan</th>
                            <th scope="col" class="py-3 px-4">Nomor Loker</th>
                            <th scope="col" class="py-3 px-4">Poin PTS</th>
                            <th scope="col" class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($allMemberships as $m)
                            <tr class="hover:bg-slate-750/50 transition">
                                <td class="py-3 px-4 font-mono font-bold text-amber-300">
                                    {{ $m->membership_number }}
                                </td>
                                <td class="py-3 px-4 text-slate-400 font-mono text-xs">
                                    User #{{ $m->user_id }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold uppercase
                                        {{ strtolower($m->tier) === 'infinity' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : (strtolower($m->tier) === 'moon' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-slate-700 text-slate-300') }}">
                                        {{ $m->tier }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-white">
                                    IDR {{ number_format($m->spend_365_days_idr) }}
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-400">
                                    {{ $m->visits_365_days }} kali
                                </td>
                                <td class="py-3 px-4 font-mono text-cyan-400">
                                    {{ $m->bottle_locker_number ?? '-' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-400">
                                    {{ number_format($m->loyalty_points) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        ● {{ ucfirst($m->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-700/60">
                {{ $allMemberships->links() }}
            </div>
        </div>

        {{-- Panel Katalog Penukaran Poin Loyalitas & Voucher Meja (D90-4) --}}
        <div class="bg-slate-800/50 rounded-2xl border border-slate-700/70 p-6 shadow-xl backdrop-blur-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-700/60">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Katalog Penukaran Poin Loyalitas & Voucher Meja VIP</span>
                        <span class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300 font-semibold">Low-Demand Nights</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Tukarkan poin lifestyle PTS untuk reservasi meja gratis pada hari Selasa - Kamis atau voucher santap F&B.
                    </p>
                </div>
                <span class="text-xs font-mono text-emerald-400">Saldo Poin: {{ number_format($membership?->loyalty_points ?? 12500) }} PTS</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="text-xs font-bold text-amber-400 uppercase">Gratis Meja VIP Pool Deck</span>
                    <h4 class="text-sm font-bold text-white">Voucher Meja Bebas Minimum Spend</h4>
                    <p class="text-xs text-slate-400">Berlaku untuk 4 orang pada hari biasa non-peak.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between items-center text-xs">
                        <span class="font-bold text-emerald-400">5.000 PTS</span>
                        <button type="button" class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition">Tukarkan</button>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="text-xs font-bold text-cyan-400 uppercase">Bottle Service Upgrade</span>
                    <h4 class="text-sm font-bold text-white">Upgrade Spirit ke Premium Champagne</h4>
                    <p class="text-xs text-slate-400">Tukar saat memesan 1 botol spirit standar.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between items-center text-xs">
                        <span class="font-bold text-emerald-400">3.500 PTS</span>
                        <button type="button" class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition">Tukarkan</button>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700 space-y-2">
                    <span class="text-xs font-bold text-purple-400 uppercase">Akomodasi Glamping Mitra</span>
                    <h4 class="text-sm font-bold text-white">Diskon 50% Menginap Festival (K16)</h4>
                    <p class="text-xs text-slate-400">Kemitraan eksklusif resort pesisir mitra venue.</p>
                    <div class="pt-2 border-t border-slate-700 flex justify-between items-center text-xs">
                        <span class="font-bold text-emerald-400">10.000 PTS</span>
                        <button type="button" class="px-3 py-1 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium transition">Tukarkan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P-UI 6: Modal Evaluasi Tingkat Member V7 --}}
    <div x-show="openEvaluateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-950/80 backdrop-blur-sm" @click="openEvaluateModal = false"></div>
            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Evaluasi Tingkat Member (V7 Engine)</h3>
                        <p class="text-xs text-slate-400">Aturan spend 365 hari, threshold kunjungan, dan alokasi loker botol.</p>
                    </div>
                    <button type="button" @click="openEvaluateModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form method="POST" action="{{ route('venue.economy.members.evaluate') }}" class="space-y-4 py-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-300">Target User ID</label>
                        <input type="number" name="target_user_id" x-model="form.user_id" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="1">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Tier Saat Ini</label>
                            <select name="current_tier" x-model="form.current_tier" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                                <option value="sun">Sun</option>
                                <option value="moon">Moon</option>
                                <option value="infinity">Infinity</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Evaluasi Anniversary?</label>
                            <select name="is_anniversary_evaluation" x-model="form.is_anniversary" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                                <option value="0">Tidak (Rolling Biasa)</option>
                                <option value="1">Ya (Anniversary Review)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Total Belanja 365 Hari IDR</label>
                            <input type="number" name="spend_365_days_idr" x-model="form.spend_365" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300">Total Kunjungan 365 Hari</label>
                            <input type="number" name="visits_365_days" x-model="form.visits_365" required class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white" min="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300">Alokasi Nomor Loker Botol (Khusus Infinity)</label>
                        <input type="text" name="assigned_locker_number" x-model="form.assigned_locker" placeholder="LCK-VIP-09" class="w-full mt-1 bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-sm text-white">
                    </div>

                    {{-- Live Evaluation Preview --}}
                    <div class="p-4 bg-slate-800/80 rounded-xl border border-amber-500/30 space-y-1.5">
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Perkiraan Hasil Tier:</span>
                            <span class="font-bold text-amber-400 uppercase" x-text="predictedTier()"></span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-300">
                            <span>Hak Loker Botol VIP:</span>
                            <span class="font-bold" :class="predictedTier() === 'infinity' ? 'text-emerald-400' : 'text-slate-500'" x-text="predictedTier() === 'infinity' ? 'Ya (Berhak)' : 'Tidak'"></span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="openEvaluateModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-xs transition">
                            Eksekusi Evaluasi V7
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function memberProfileDashboard() {
    return {
        openEvaluateModal: false,
        toast: {
            visible: false,
            type: 'success',
            title: '',
            message: ''
        },
        form: {
            user_id: 1,
            current_tier: 'moon',
            is_anniversary: '0',
            spend_365: 80000000,
            visits_365: 25,
            assigned_locker: 'LCK-VIP-10'
        },
        predictedTier() {
            const spend = Number(this.form.spend_365 || 0);
            const visits = Number(this.form.visits_365 || 0);
            if (spend >= 75000000 && visits >= 24) return 'infinity';
            if (spend >= 20000000 || visits >= 12) return 'moon';
            return 'sun';
        }
    };
}
</script>
@endsection
