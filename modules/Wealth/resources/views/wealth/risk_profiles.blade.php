@extends('layouts.app')

@section('title', 'Wealth Risk Profiling & Financial Baselines')
@section('subtitle', 'Penentuan Profil Resiko Investor, Target Dana Darurat 6-Bulan & Personalisasi Portofolio')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Kalkulasi Profil Resiko Baru</h3>
            <form action="{{ route('wealth.treasury.compute-profile') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Pengeluaran Bulanan (IDR)</label>
                    <input type="number" name="monthly_spend_baseline_idr" value="10000000" min="100000" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Skor Kuesioner Resiko (0 - 100)</label>
                    <input type="number" name="risk_survey_score" value="65" min="0" max="100" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-sm">
                    Hitung Profil Resiko & Dana Darurat
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Profil Investor Terdaftar</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">Investor</th>
                            <th class="p-3">Profil Resiko</th>
                            <th class="p-3">Target Dana Darurat</th>
                            <th class="p-3">Pengeluaran/Bulan</th>
                            <th class="p-3">Auto Invest</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($profiles as $p)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-semibold text-slate-200">{{ optional($p->user)->name ?? "User #{$p->user_id}" }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded {{ $p->risk_profile === 'aggressive' ? 'bg-rose-500/20 text-rose-400' : ($p->risk_profile === 'moderate' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400') }} uppercase">
                                        {{ $p->risk_profile }}
                                    </span>
                                </td>
                                <td class="p-3 font-mono text-emerald-400 font-bold">Rp {{ number_format($p->emergency_fund_target_idr) }}</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($p->monthly_spend_baseline_idr) }}</td>
                                <td class="p-3">
                                    <span class="text-xs {{ $p->auto_invest_enabled ? 'text-emerald-400' : 'text-slate-500' }}">
                                        {{ $p->auto_invest_enabled ? 'AKTIF' : 'NON-AKTIF' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">Belum ada profil investor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $profiles->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
