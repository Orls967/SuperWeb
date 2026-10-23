@extends('layouts.app')

@section('title', 'Embedded Micro-Insurance Policies')
@section('subtitle', 'Penerbitan Polis Asuransi Mikro Otomatis Tersemat (Point-of-Sale / Ride / Charging / Lease)')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Penerbitan Polis Tersemat Baru</h3>
            <form action="{{ route('insurance.workflow.issue-policy') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Produk Asuransi</label>
                    <select name="product_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->premium_amount_idr) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tipe Objek Tanggungan</label>
                    <input type="text" name="subject_ref_type" value="VEHICLE" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">ID Objek Tanggungan</label>
                    <input type="text" name="subject_ref_id" placeholder="misal: VEH-101" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Masa Berlaku (Hari)</label>
                    <input type="number" name="duration_days" value="30" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm text-slate-100">
                </div>
                <button type="submit" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-sm">
                    Terbitkan Polis Sekarang
                </button>
            </form>
        </div>

        <div class="md:col-span-2 p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-base font-bold text-slate-100 mb-4">Daftar Polis Terbit Aktif</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase bg-slate-900/50 text-slate-400 border-b border-slate-700">
                        <tr>
                            <th class="p-3">No. Polis</th>
                            <th class="p-3">Produk</th>
                            <th class="p-3">Premi</th>
                            <th class="p-3">Batas Santunan</th>
                            <th class="p-3">Kadaluarsa</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($policies as $pol)
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-3 font-mono font-bold text-violet-400 text-xs">{{ $pol->policy_number }}</td>
                                <td class="p-3 text-xs">{{ optional($pol->product)->name ?? '-' }}</td>
                                <td class="p-3 font-mono text-xs">Rp {{ number_format($pol->premium_paid_idr) }}</td>
                                <td class="p-3 font-mono text-xs text-emerald-400">Rp {{ number_format($pol->coverage_limit_idr) }}</td>
                                <td class="p-3 text-xs text-slate-400">{{ optional($pol->expires_at)->format('d M Y') ?? '-' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 text-xs font-bold rounded bg-emerald-500/20 text-emerald-400 uppercase">
                                        {{ $pol->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">Belum ada polis diterbitkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $policies->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
