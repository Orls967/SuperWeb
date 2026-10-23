@extends('layouts.app')

@section('title', 'Corporate Idle Cash Sweep & Overnight Yield')
@section('subtitle', 'Penyapuan Likuiditas Kas Menganggur Korporasi ke Reksa Dana Pasar Uang Imbal Hasil Harian')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Eksekusi Cash Sweep</h3>
            <form action="{{ route('wealth.treasury.sweep-cash') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Pilih Akun / Profil Korporasi</label>
                    <select name="profile_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($profiles as $pr)
                            <option value="{{ $pr->id }}">{{ optional($pr->user)->name ?? "Profile #{$pr->id}" }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Kas Operasional Tersedia (IDR)</label>
                    <input type="number" name="available_cash_idr" value="75000000" min="1000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Batas Likuiditas Wajib (IDR Buffer)</label>
                    <input type="number" name="buffer_idr" value="20000000" min="0" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm">
                    Sapu Kas Menganggur ke Reksa Dana
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-3">Keuntungan Program Corporate Treasury Cash Sweep</h3>
            <div class="space-y-3 text-xs text-slate-300">
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-emerald-400 mb-1">Optimalisasi Imbal Hasil Tanpa Risiko Likuiditas</p>
                    <p class="text-slate-400">Kas operasional di atas batas minimum otomatis disapu ke instrumen pasar uang berperingkat AAA dengan imbal hasil 5.5% - 6.2% per tahun yang dapat dicairkan T+0.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800">
                    <p class="font-bold text-blue-400 mb-1">Pencatatan Akuntansi PSAK Terotomasi</p>
                    <p class="text-slate-400">Setiap order sweep dan return akrual bunga langsung terefleksi di pembukuan buku besar (general ledger) tanpa intervensi manual tim akunting.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
